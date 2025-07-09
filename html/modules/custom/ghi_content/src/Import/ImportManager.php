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
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileRepositoryInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\ghi_content\Plugin\Block\Paragraph;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentImageInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Public constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, AccountInterface $current_user, UuidInterface $uuid, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, SelectionPluginManager $selection_plugin_manager, FileRepositoryInterface $file_repository, EventDispatcherInterface $event_dispatcher) {
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->currentUser = $current_user;
    $this->uuidGenerator = $uuid;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->selectionPluginManager = $selection_plugin_manager;
    $this->fileRepository = $file_repository;
    $this->eventDispatcher = $event_dispatcher;
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
      $container->get('event_dispatcher'),
    );
  }

  /**
   * Import a text field for the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be imported/synced.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentInterface $content
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
  public function importTextfield(NodeInterface $node, RemoteContentInterface $content, $label, $method, $field_name, $format = 'plain_text', ?MessengerInterface $messenger = NULL) {
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
  public function importImage(NodeInterface $node, RemoteContentImageInterface $content, $field_name = 'field_image', ?MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }
    $message = NULL;
    $image_url = $content->getImageUri();
    /** @var \Drupal\file\FileInterface $local_file */
    $local_file = !$node->get($field_name)->isEmpty() ? $this->entityTypeManager->getStorage('file')->load($node->get($field_name)->target_id) : NULL;
    if (!empty($image_url)) {
      $caption = $content->getImageCaptionPlain();

      // Call an event to sanitize the filename.
      $event = new FileUploadSanitizeNameEvent(basename($image_url), '');
      $this->eventDispatcher->dispatch($event);
      $image_name = $event->getFilename();

      // Get the remote and local file size.
      $file_size_remote = $content->getSource()->getFileSize($image_url);
      // Use PHPs built-in filesize instead of File::getFielSize because like
      // that we check if the file is actually there.
      $file_size_local = $local_file ? @filesize($local_file->getFileUri()) : NULL;
      if ($file_size_remote == $file_size_local) {
        // Image already present and downloaded or both images unavailable. No
        // need for further action.
        return FALSE;
      }

      $data = $content->getSource()->getFileContent($image_url);
      if (!empty($data)) {
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
        $message = $this->t('Error retrieving image');
        $node->get($field_name)->setValue(NULL);
      }
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
  public function importArticleParagraphs(NodeInterface $node, RemoteArticleInterface $article, array $paragraph_ids = [], ?MessengerInterface $messenger = NULL, $cleanup = FALSE) {

    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }

    $sections = $this->getNodeSections($node);
    $delta = 0;
    $section = &$sections[$delta];

    $existing_paragraphs_count = count(array_filter(array_map(function ($component) {
      return $component->getPlugin() instanceof Paragraph;
    }, $section->getComponents())));

    if ($cleanup && $section->getComponents()) {
      foreach ($section->getComponents() as $component) {
        $section->removeComponent($component->getUuid());
      }
    }

    $paragraph_uuids = [];
    $new_components = [];
    $remote_paragraphs = $article->getParagraphs();
    foreach ($remote_paragraphs as $paragraph) {
      if (!empty($paragraph_ids) && !in_array($paragraph->getId(), $paragraph_ids)) {
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
        $messages[] = $this->t('Updated %plugin_title (%uuid)', [
          '%plugin_title' => $definition['admin_label'],
          '%uuid' => $paragraph->getUuid(),
        ]);
        $config = array_filter([
          'id' => $definition['id'],
          'provider' => $definition['provider'],
          'sync' => [
            'source_uuid' => $paragraph->getUuid(),
          ],
        ]);
        $configuration = $config + $paragraph_configuration + $context_mapping + $existing_component->get('configuration');
        $existing_component->setConfiguration($configuration);
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
        $new_components[] = $component;
        $section->appendComponent($component);
        $paragraph_uuids[] = $component->getUuid();
      }
    }

    if ($existing_paragraphs_count && !empty($new_components)) {
      $this->positionNewParagraphs($section, $new_components, $remote_paragraphs);
      if ($node instanceof ContentReviewInterface) {
        // Articles where paragraphs have been added to existing articles
        // need to be reviewed.
        $node->needsReview(TRUE);
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
  public function importDocumentChapters(NodeInterface $node, RemoteDocumentInterface $document, array $chapter_ids = [], ?MessengerInterface $messenger = NULL, $cleanup = FALSE) {

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
    $chapters = $document->getChapters(FALSE);
    foreach ($chapters as $chapter) {
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
      $existing_configuration = $existing_component?->get('configuration')['hpc'] ?? [];
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
          'display' => [
            'label' => $existing_configuration['display']['label'] ?? NULL,
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
   * Import tags for a content entity.
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
  public function importTags(NodeInterface $node, RemoteContentInterface $content, $field_name = 'field_tags', ?MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }

    // Get the tags.
    $content_space_tags = $content->getContentSpaceTags() ?? [];
    $content_tags = $content->getContentTags() ?? [];

    $update = !$node->get($field_name)->isEmpty();

    /** @var \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection $handler */
    $handler = $this->selectionPluginManager->getInstance([
      // Restrict selection of terms to a single vocabulary.
      'target_type' => 'taxonomy_term',
      'target_bundles' => [
        'tags' => 'tags',
      ],
    ]);
    $remote_source = $content->getSource();
    $terms = array_filter(array_map(function ($tag) use ($handler, $remote_source) {
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
      // Make sure that the term has a type.
      if ($term instanceof TermInterface && $term->get('field_type')->isEmpty() && $tag_entity = $remote_source->getTag($tag)) {
        $term->set('field_type', $tag_entity->getType())->save();
      }
      return $term->id() ? $term : NULL;
    }, array_unique(array_merge($content_space_tags, $content_tags))));

    $node->get($field_name)->setValue($terms);

    if ($messenger !== NULL) {
      $messenger->addMessage($update ? $this->t('Updated tags') : $this->t('Imported tags'));
    }

    return TRUE;
  }

  /**
   * Import the content space for a content entity.
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
  public function importContentSpace(NodeInterface $node, RemoteContentInterface $content, $field_name = 'field_content_space', ?MessengerInterface $messenger = NULL) {
    if (!$node->hasField($field_name)) {
      return FALSE;
    }

    // Get the content space.
    $content_space = $content->getContentSpace();

    $update = !$node->get($field_name)->isEmpty();

    /** @var \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection $handler */
    $handler = $this->selectionPluginManager->getInstance([
      // Restrict selection of terms to a single vocabulary.
      'target_type' => 'taxonomy_term',
      'target_bundles' => [
        'content_space' => 'content_space',
      ],
    ]);
    $terms = array_filter(array_map(function ($term_name) use ($handler) {
      $matches = $handler->getReferenceableEntities($term_name, '=', 1);
      $term = NULL;
      if (!empty($matches) && !empty($matches['content_space']) && count($matches['content_space']) == 1) {
        $term_id = array_key_first($matches['content_space']);
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      }
      if (!$term) {
        $term = $handler->createNewEntity('taxonomy_term', 'content_space', $term_name, $this->currentUser->id());
        $term->save();
      }
      return $term->id() ? $term : NULL;
    }, [$content_space]));

    $node->get($field_name)->setValue($terms);

    if ($messenger !== NULL) {
      $messenger->addMessage($update ? $this->t('Updated content space') : $this->t('Imported content space'));
    }

    return TRUE;
  }

  /**
   * Get the new component order, based on a single new component.
   *
   * The logic is this:
   *  - Get the previous and following paragraphs as currently defined in the
   *    remote source.
   *  - If there is a preceding paragraph, place the newly added paragraph
   *    after the preceding one in the HA article page.
   *  - Else if there is a following paragraph, place the newly added paragraph
   *    before the following one in the HA article page.
   *  - Otherwise, keep the current behaviour which places the newly added
   *    paragraph as the last element on the HA article page.
   *
   * @param \Drupal\layout_builder\SectionComponent[] $components
   *   The full set of components, including the new one.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The newly added component.
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface[] $paragraphs
   *   An array of remote paragraphs of the remote article in the order
   *   defined on the remote.
   * @param \Drupal\layout_builder\SectionComponent[] $exclude_components
   *   An array of components to exclude from the position logic.
   *
   * @return array|false
   *   An array of uuids of section components in the new order.
   */
  private function getNewComponentOrder(array $components, SectionComponent $component, array $paragraphs, $exclude_components = []) {
    $plugin = $component->getPlugin();
    if (!$plugin instanceof Paragraph) {
      return FALSE;
    }
    $paragraph = $plugin->getParagraph();
    if (!$paragraph) {
      return FALSE;
    }

    // $exclude_component_uuids = [];
    $exclude_component_uuids = array_map(function ($exclude_component) {
      return $exclude_component->getUuid();
    }, $exclude_components);

    // Get an array of section component weights keyed by the section
    // component uuids. Ignore components listed in $exclude_components.
    $component_weights = [];
    $paragraphs_by_uuids = [];
    foreach ($components as $weight => $_component) {
      if (!empty($exclude_component_uuids) && $_component->getUuid() && in_array($_component->getUuid(), $exclude_component_uuids)) {
        continue;
      }
      $plugin = $_component->getPlugin();
      $component_weights[$_component->getUuid()] = $weight;
      $paragraphs_by_uuids[$_component->getUuid()] = $plugin instanceof Paragraph ? $plugin->getParagraph()?->getId() : NULL;
    }
    asort($component_weights);

    // Ignore all paragraphs not listed in $paragraphs_by_uuids.
    $paragraphs = array_filter($paragraphs, function (RemoteParagraphInterface $_paragraph) use ($paragraphs_by_uuids, $paragraph) {
      return in_array($_paragraph->getId(), $paragraphs_by_uuids) || $_paragraph->getId() == $paragraph->getId();
    });

    // Find the surrounding paragraphs in the original (filtered) array of
    // paragraphs.
    $paragraph_ids = array_keys($paragraphs);
    $position = array_search($paragraph->getId(), $paragraph_ids);
    $previous_paragraph_position = NULL;
    if ($position > 0) {
      $previous_paragraphs = array_slice($paragraph_ids, 0, $position);
      $previous_paragraphs = array_filter($previous_paragraphs, function ($paragraph_id) use ($paragraphs_by_uuids) {
        return in_array($paragraph_id, $paragraphs_by_uuids);
      });
      $previous_paragraph_position = count($previous_paragraphs) ? end($previous_paragraphs) : NULL;
    }
    $following_paragraph_position = NULL;
    if ($position < count($paragraph_ids) - 1) {
      $following_paragraphs = array_slice($paragraph_ids, $position + 1);
      $following_paragraphs = array_filter($following_paragraphs, function ($paragraph_id) use ($paragraphs_by_uuids) {
        return in_array($paragraph_id, $paragraphs_by_uuids);
      });
      $following_paragraph_position = count($following_paragraphs) ? reset($following_paragraphs) : NULL;
    }

    // Find the position where to add the new component.
    $component_order = array_values(array_flip($component_weights));
    $split_index = NULL;
    if ($previous_paragraph_position && $previous_uuid = array_search($previous_paragraph_position, $paragraphs_by_uuids)) {
      $split_index = array_search($previous_uuid, $component_order) + 1;
    }
    elseif ($following_paragraph_position && $following_uuid = array_search($following_paragraph_position, $paragraphs_by_uuids)) {
      $split_index = array_search($following_uuid, $component_order);
    }

    // And setup the new order according to the previous results and update
    // the section component weights.
    $new_order = FALSE;
    if ($split_index !== NULL) {
      $new_order = [];
      // Remove the new component from the ordered list of component weights.
      unset($component_weights[$component->getUuid()]);
      // Set the new order by inserting the uuid of the new component in the
      // right place.
      $new_order = array_merge(
        array_slice(array_flip($component_weights), 0, $split_index),
        [$component->getUuid()],
        array_slice(array_flip($component_weights), $split_index),
      );
    }
    return $new_order;
  }

  /**
   * Position the given components in the section.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section of the new component.
   * @param \Drupal\layout_builder\SectionComponent[] $new_components
   *   The newly added component.
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface[] $paragraphs
   *   An array of remote paragraphs of the remote article in the order
   *   defined on the remote.
   *
   * @return array|false
   *   An array of uuids of section components in the new order.
   */
  private function positionNewParagraphs(Section $section, array $new_components, array $paragraphs) {
    $new_order = FALSE;
    $components = array_values($section->getComponents());
    ArrayHelper::sortObjectsByMethod($components, 'getWeight');
    $components = array_values($components);

    foreach ($new_components as $key => $component) {
      $new_order = $this->getNewComponentOrder($components, $component, $paragraphs, $key < count($new_components) - 1 ? array_slice($new_components, $key + 1) : []);
      if (!$new_order) {
        continue;
      }
      $components = [];
      foreach ($new_order as $uuid) {
        $components[] = $section->getComponent($uuid);
      }
    }
    if (empty($new_order)) {
      return $new_order;
    }
    foreach ($new_order as $weight => $uuid) {
      $section->getComponent($uuid)->setWeight($weight);
    }
    return $new_order;
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
  public function setupRelatedArticlesElement(NodeInterface $node, RemoteArticleInterface $article, ?MessengerInterface $messenger = NULL) {
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
   * This also supports finding existing components if the given content item
   * is set to replace another one via it's "replaces" configuration key.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface $content_item
   *   The paragraph object from the remote source.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   Either a matching component or NULL.
   */
  private function getExistingSyncedContentItem(NodeInterface $node, RemoteContentItemBaseInterface $content_item) {
    $replaces = $content_item->getConfiguration()['replaces'] ?? NULL;
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    foreach ($sections[0]->getComponents() as $component) {
      $configuration = $component->get('configuration');
      $source_uuid = $configuration['sync']['source_uuid'] ?? NULL;
      if ($source_uuid && ($source_uuid == $content_item->getUuid() || ($replaces && $replaces == $source_uuid))) {
        $configuration['sync']['source_uuid'] = $content_item->getUuid();
        $component->setConfiguration($configuration);
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
  public function layoutManagerDiscardChanges(NodeInterface $node, ?MessengerInterface $messenger = NULL) {
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
