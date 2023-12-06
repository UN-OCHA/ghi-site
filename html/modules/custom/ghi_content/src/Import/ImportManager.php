<?php

namespace Drupal\ghi_content\Import;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileRepositoryInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentImageInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync element service class.
 */
class ImportManager implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;
  use LayoutEntityHelperTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The Drupal account to use for checking for access to block.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The UUID generator service.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager
   */
  protected $selectionPluginManager;

  /**
   * File system service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Public constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, AccountInterface $current_user, UuidInterface $uuid, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, SelectionPluginManager $selection_plugin_manager, FileRepositoryInterface $file_repository) {
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->currentUser = $current_user;
    $this->uuidGenerator = $uuid;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->selectionPluginManager = $selection_plugin_manager;
    $this->fileRepository = $file_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('current_user'),
      $container->get('uuid'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('file.repository'),
    );
  }

  /**
   * Import a text field for the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentImageInterface $content
   *   The content object as retrieved from the remote source.
   * @param string $label
   *   The label of the field.
   * @param string $method
   *   The name of the source field.
   * @param string $field_name
   *   The field name of the target field.
   * @param string $format
   *   The format for the content.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   */
  public function importTextfield(NodeInterface $node, RemoteContentImageInterface $content, $label, $method, $field_name, $format = 'plain_text', MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }
    $message = NULL;
    $value = method_exists($content, $method) ? $content->{$method}() : NULL;
    $t_args = [
      '@label' => strtolower($label),
    ];

    $field_config = $node->get($field_name)->getFieldDefinition();
    $field_type = $field_config->getType();
    if ($value) {
      $update = !$node->get($field_name)->isEmpty();
      $node->get($field_name)->setValue($field_type == 'string' ? $value : [
        'value' => $value,
        'format' => $format,
      ]);
      $message = $update ? $this->t('Updated @label', $t_args) : $this->t('Imported @label', $t_args);
    }
    else {
      if (!$node->get($field_name)->isEmpty()) {
        $message = $this->t('Removed @label', $t_args);
      }
      $node->get($field_name)->setValue(NULL);
    }

    if ($messenger !== NULL && $message) {
      $messenger->addMessage($message);
    }
  }

  /**
   * Import an image for the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentImageInterface $content
   *   The content object as retrieved from the remote source.
   * @param string $field_name
   *   The field name of the target field for the image.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   */
  public function importImage(NodeInterface $node, RemoteContentImageInterface $content, $field_name = 'field_image', MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }
    $message = NULL;
    $image_url = $content->getImageUri();
    if (!empty($image_url)) {
      $caption = $content->getImageCaptionPlain();
      $image_name = basename($image_url);
      $data = $content->getSource()->getFileContent($image_url);
      $file = $this->fileRepository->writeData($data, ArticleManager::IMAGE_DIRECTORY . '/' . $image_name, FileSystem::EXISTS_REPLACE);
      $update = !$node->get($field_name)->isEmpty();
      $node->get($field_name)->setValue([
        'target_id' => $file->id(),
        'alt' => $caption ? Unicode::truncate($caption, 512, TRUE, TRUE) : $node->getTitle(),
        'title' => NULL,
      ]);
      $message = $update ? $this->t('Updated image') : $this->t('Imported image');
    }
    else {
      if (!$node->get($field_name)->isEmpty()) {
        $message = $this->t('Removed image');
      }
      $node->get($field_name)->setValue(NULL);
    }

    if ($messenger !== NULL && $message) {
      $messenger->addMessage($message);
    }
  }

  /**
   * Import article paragraphs into a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   The article object as retrieved from the remote source.
   * @param array $paragraph_ids
   *   An array of paragraph ids to process. These must appear in
   *   $article->getParagraphs().
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   * @param bool $cleanup
   *   Whether existing elements should be cleaned up first.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function importArticleParagraphs(NodeInterface $node, RemoteArticleInterface $article, array $paragraph_ids = [], MessengerInterface $messenger = NULL, $cleanup = FALSE) {

    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }

    $sections = $this->getNodeSections($node);
    $delta = 0;

    if ($cleanup && $sections[$delta]->getComponents()) {
      foreach ($sections[$delta]->getComponents() as $component) {
        $sections[$delta]->removeComponent($component->getUuid());
      }
    }

    $paragraph_uuids = [];
    foreach ($article->getParagraphs() as $paragraph) {
      if (!empty($paragraph_ids) && !in_array($paragraph->id, $paragraph_ids)) {
        continue;
      }

      $definition = $this->getParagraphPluginDefintion();
      $context_mapping = [
        'context_mapping' => [
          'node' => 'layout_builder.entity',
        ],
      ];

      $messages = [];

      $existing_component = $this->getExistingSyncedContentItem($node, $paragraph);
      $paragraph_configuration = [
        'hpc' => [
          'article_select' => [
            'article' => [
              'remote_source' => $article->getSource()->getPluginId(),
              'article_id' => $article->getId(),
            ],
          ],
          'paragraph' => [
            'paragraph_id' => [$paragraph->getId()],
          ],
        ],
        'lock_article' => TRUE,
      ];
      if ($existing_component) {
        // Update an existing component.
        $configuration = $paragraph_configuration + $context_mapping + $existing_component->get('configuration');
        $existing_component->setConfiguration($configuration);
        $messages[] = $this->t('Updated %plugin_title (%uuid)', [
          '%plugin_title' => $definition['admin_label'],
          '%uuid' => $paragraph->getUuid(),
        ]);
        $paragraph_uuids[] = $existing_component->getUuid();
      }
      else {
        // Append a new component.
        $messages[] = $this->t('Added %plugin_title (%uuid)', [
          '%plugin_title' => $definition['admin_label'],
          '%uuid' => $paragraph->getUuid(),
        ]);
        $config = array_filter([
          'id' => $definition['id'],
          'provider' => $definition['provider'],
          'sync' => [
            'source_uuid' => $paragraph->getUuid(),
          ],
        ]) + $context_mapping;
        $config += $paragraph_configuration;
        $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
        $sections[$delta]->appendComponent($component);
        $paragraph_uuids[] = $component->getUuid();
      }

    }

    // Always cleanup paragraphs that have been removed from the source.
    $definition = $this->getParagraphPluginDefintion();
    foreach ($sections[$delta]->getComponents() as $component) {
      if ($component->getPluginId() != $definition['id']) {
        // Not a paragraph, ignore.
        continue;
      }
      /** @var \Drupal\ghi_content\Plugin\Block\Paragraph */
      $plugin = $component->getPlugin();
      if (!$plugin->getArticle() || $plugin->getArticle()->getId() != $article->getId()) {
        // Only remove pragraphs from the same article. This allows to add more
        // paragraphs from different articles.
        // @todo Good idea?
        continue;
      }
      if (in_array($component->getUuid(), $paragraph_uuids)) {
        continue;
      }
      $sections[$delta]->removeComponent($component->getUuid());
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);

    if ($messenger !== NULL && count($messages)) {
      foreach ($messages as $message) {
        $messenger->addMessage($message);
      }
    }

    return TRUE;
  }

  /**
   * Import document chapters into a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface $document
   *   The document object as retrieved from the remote source.
   * @param array $chapter_ids
   *   An array of chapter ids to process. These must appear in
   *   $document->getChapters().
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   * @param bool $cleanup
   *   Whether existing elements should be cleaned up first.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function importDocumentChapters(NodeInterface $node, RemoteDocumentInterface $document, array $chapter_ids = [], MessengerInterface $messenger = NULL, $cleanup = FALSE) {

    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }

    $sections = $this->getNodeSections($node);
    $delta = 0;

    if ($cleanup && $sections[$delta]->getComponents()) {
      foreach ($sections[$delta]->getComponents() as $component) {
        $sections[$delta]->removeComponent($component->getUuid());
      }
    }

    $chapter_uuids = [];
    foreach ($document->getChapters(FALSE) as $chapter) {
      if (!empty($chapter_ids) && !in_array($chapter->id, $chapter_ids)) {
        continue;
      }

      $definition = $this->getChapterPluginDefintion();
      $context_mapping = [
        'context_mapping' => [
          'node' => 'layout_builder.entity',
        ],
      ];

      $messages = [];

      $existing_component = $this->getExistingSyncedContentItem($node, $chapter);
      $chapter_configuration = [
        'hpc' => [
          'document_select' => [
            'document' => [
              'remote_source' => $document->getSource()->getPluginId(),
              'document_id' => $document->getId(),
            ],
          ],
          'chapter' => [
            'chapter_id' => $chapter->getId(),
          ],
        ],
        'lock_document' => TRUE,
      ];
      if ($existing_component && ($existing_component->get('configuration')['lock_document'] ?? FALSE)) {
        // Update an existing component.
        $configuration = $chapter_configuration + $context_mapping + $existing_component->get('configuration');
        $existing_component->setConfiguration($configuration);
        $messages[] = $this->t('Updated %plugin_title (%uuid)', [
          '%plugin_title' => $definition['admin_label'],
          '%uuid' => $chapter->getUuid(),
        ]);
        $chapter_uuids[] = $existing_component->getUuid();
      }
      else {
        // Append a new component.
        $messages[] = $this->t('Added %plugin_title (%uuid)', [
          '%plugin_title' => $definition['admin_label'],
          '%uuid' => $chapter->getUuid(),
        ]);
        $config = array_filter([
          'id' => $definition['id'],
          'provider' => $definition['provider'],
          'sync' => [
            'source_uuid' => $chapter->getUuid(),
          ],
        ]) + $context_mapping;
        $config += $chapter_configuration;
        $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
        $sections[$delta]->appendComponent($component);
        $chapter_uuids[] = $component->getUuid();
      }

    }

    // Always cleanup chapters that have been removed from the source.
    $definition = $this->getChapterPluginDefintion();
    foreach ($sections[$delta]->getComponents() as $component) {
      if ($component->getPluginId() != $definition['id']) {
        // Not a chapter, ignore.
        continue;
      }
      /** @var \Drupal\ghi_content\Plugin\Block\DocumentChapter */
      $plugin = $component->getPlugin();
      if (!$plugin->getDocument() || $plugin->getDocument()->getId() != $document->getId()) {
        // Only remove chapters from the same document. This allows to add more
        // chapters from different articles.
        // @todo Good idea?
        continue;
      }
      if (!($component->get('configuration')['lock_document'] ?? FALSE)) {
        // Don't remove chapters that do not have the document locked, those
        // are manually added and need to be kept.
        continue;
      }
      if (in_array($component->getUuid(), $chapter_uuids)) {
        continue;
      }
      $sections[$delta]->removeComponent($component->getUuid());
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);

    if ($messenger !== NULL && count($messages)) {
      foreach ($messages as $message) {
        $messenger->addMessage($message);
      }
    }

    return TRUE;
  }

  /**
   * Import tags for an content object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which tags should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentInterface $content
   *   The content object as retrieved from the remote source.
   * @param string $field_name
   *   The field name into which the tags should be imported.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   */
  public function importTags(NodeInterface $node, RemoteContentInterface $content, $field_name = 'field_tags', MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }

    // Get the tags.
    $main_tags = $content->getMajorTags() ?? [];
    $content_tags = $content->getMinorTags() ?? [];

    $update = !$node->get($field_name)->isEmpty();

    /** @var \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection $handler */
    $handler = $this->selectionPluginManager->getInstance([
      // Restrict selection of terms to a single vocabulary.
      'target_type' => 'taxonomy_term',
      'target_bundles' => [
        'tags' => 'tags',
      ],
    ]);
    $terms = array_filter(array_map(function ($tag) use ($handler) {
      $matches = $handler->getReferenceableEntities($tag, '=', 1);
      $term = NULL;
      if (!empty($matches) && !empty($matches['tags']) && count($matches['tags']) == 1) {
        $term_id = array_key_first($matches['tags']);
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      }
      if (!$term) {
        $term = $handler->createNewEntity('taxonomy_term', 'tags', $tag, $this->currentUser->id());
        $term->save();
      }
      return $term->id() ? $term : NULL;
    }, array_unique(array_merge($main_tags, $content_tags))));

    $node->get($field_name)->setValue($terms);

    if ($messenger !== NULL) {
      $messenger->addMessage($update ? $this->t('Updated tags') : $this->t('Imported tags'));
    }

    return TRUE;
  }

  /**
   * Setup the related articles element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which tags should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   The article object as retrieved from the remote source.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   */
  public function setupRelatedArticlesElement(NodeInterface $node, RemoteArticleInterface $article, MessengerInterface $messenger = NULL) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }

    $sections = $this->getNodeSections($node);
    $delta = 0;
    $definition = $this->blockManager->getDefinition('related_articles', FALSE);
    if (!$definition) {
      return;
    }

    $configuration = [
      'hpc' => [
        'mode' => 'fixed',
        'count' => 3,
        'select' => [
          'order' => NULL,
          'selected' => [],
        ],
      ],
    ];

    $existing_components = $this->getExistingComponentsByType($node, $definition['id']);
    if (!empty($existing_components)) {
      // Only do this once.
      return;
    }

    $context_mapping = [
      'context_mapping' => [
        'node' => 'layout_builder.entity',
      ],
    ];

    // Append a new component.
    $messages[] = $this->t('Added %plugin_title', [
      '%plugin_title' => $definition['admin_label'],
    ]);
    $config = array_filter([
      'id' => $definition['id'],
      'provider' => $definition['provider'],
    ]) + $context_mapping;
    $config += $configuration;
    $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
    $sections[$delta]->appendComponent($component);

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);

    if ($messenger !== NULL && count($messages)) {
      foreach ($messages as $message) {
        $messenger->addMessage($message);
      }
    }

    return TRUE;
  }

  /**
   * Get the plugin definition for a paragraph block.
   *
   * @return mixed
   *   A plugin definition, or NULL if it can't be found
   */
  public function getParagraphPluginDefintion() {
    return $this->blockManager->getDefinition('paragraph', FALSE);
  }

  /**
   * Get the plugin definition for a chapter block.
   *
   * @return mixed
   *   A plugin definition, or NULL if it can't be found
   */
  public function getChapterPluginDefintion() {
    return $this->blockManager->getDefinition('document_chapter', FALSE);
  }

  /**
   * Find a section component corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface $content_item
   *   The paragraph object from the remote source.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   Either a matching component, or NULL.
   */
  private function getExistingSyncedContentItem(NodeInterface $node, RemoteContentItemBaseInterface $content_item) {
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    foreach ($sections[0]->getComponents() as $component) {
      $configuration = $component->get('configuration');
      if (!empty($configuration['sync']) && !empty($configuration['sync']['source_uuid']) && $configuration['sync']['source_uuid'] == $content_item->getUuid()) {
        return $component;
      }
    }
    return NULL;
  }

  /**
   * Find a section component corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param string $plugin_id
   *   The plugin id of the component to search.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   Either a matching component, or NULL.
   */
  private function getExistingComponentsByType(NodeInterface $node, $plugin_id) {
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    $components = [];
    foreach ($sections[0]->getComponents() as $component) {
      if ($component->getPluginId() == $plugin_id) {
        $components[] = $component;
      }
    }
    return $components;
  }

  /**
   * Get sections for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\layout_builder\Section[]|null
   *   An array of layout builder sections.
   */
  public function getNodeSections(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage) {
      return NULL;
    }
    $sections = $section_storage->getSections();
    return $sections;
  }

  /**
   * Clear layout builders shared temp store.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be synced.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   */
  public function layoutManagerDiscardChanges(NodeInterface $node, MessengerInterface $messenger = NULL) {
    $section_storage = $this->getSectionStorageForEntity($node);
    // @todo See if the view mode can be retrieved somehow.
    $section_storage->setContextValue('view_mode', 'default');
    $this->layoutTempstoreRepository->delete($section_storage);
    if ($messenger !== NULL) {
      $messenger->addMessage($this->t('Cleared layout builder temporary storage'));
    }
  }

  /**
   * Get the UUIDs of the local paragraphs.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The local node object.
   *
   * @return string[]
   *   An array of uuids, sorted alphabetically.
   */
  public function getLocalArticleParagraphUuids(NodeInterface $node) {
    $uuids = [];
    $definition = $this->getParagraphPluginDefintion();
    $sections = $this->getNodeSections($node);
    foreach ($sections[0]->getComponents() as $component) {
      if ($component->getPluginId() != $definition['id']) {
        continue;
      }
      $configuration = $component->get('configuration');
      if (empty($configuration['sync']) || empty($configuration['sync']['source_uuid'])) {
        continue;
      }
      $uuids[] = $configuration['sync']['source_uuid'];
    }
    sort($uuids);
    return $uuids;
  }

  /**
   * Get the UUIDs of the remote paragraphs.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   The remote article object.
   *
   * @return string[]
   *   An array of uuids, sorted alphabetically.
   */
  public function getRemoteArticleParagraphUuids(RemoteArticleInterface $article) {
    $uuids = [];
    foreach ($article->getParagraphs() as $paragraph) {
      $uuids[] = $paragraph->getUuid();
    }
    sort($uuids);
    return $uuids;
  }

  /**
   * Get the UUIDs of the local chapters.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The local node object.
   *
   * @return string[]
   *   An array of uuids, sorted alphabetically.
   */
  public function getLocalDocumentChapterUuids(NodeInterface $node) {
    $uuids = [];
    $definition = $this->getChapterPluginDefintion();
    $sections = $this->getNodeSections($node);
    foreach ($sections[0]->getComponents() as $component) {
      if ($component->getPluginId() != $definition['id']) {
        continue;
      }
      $configuration = $component->get('configuration');
      if (empty($configuration['sync']) || empty($configuration['sync']['source_uuid'])) {
        continue;
      }
      $uuids[] = $configuration['sync']['source_uuid'];
    }
    sort($uuids);
    return $uuids;
  }

  /**
   * Get the UUIDs of the remote chapters.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface $document
   *   The remote document object.
   *
   * @return string[]
   *   An array of uuids, sorted alphabetically.
   */
  public function getRemoteDocumentChapterUuids(RemoteDocumentInterface $document) {
    $uuids = [];
    foreach ($document->getChapters(FALSE) as $chapter) {
      $uuids[] = $chapter->getUuid();
    }
    sort($uuids);
    return $uuids;
  }

}
