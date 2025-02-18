<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_content\Controller\OrphanedContentController;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_sections\Entity\ImageNodeInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle base class for content nodes, e.g. articles or documents.
 */
abstract class ContentBase extends Node implements NodeInterface, ImageNodeInterface {

  use ContentPathTrait;

  /**
   * A context node. If set, this will change links created using toLink().
   *
   * @var \Drupal\node\NodeInterface
   */
  private $contextNode = NULL;

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $orphan_operations = [
      'view',
      'delete',
    ];
    if ($this->isOrphaned() && !in_array($operation, $orphan_operations)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    if ($operation == 'delete' && !$account->hasPermission('administer site configuration')) {
      if ($this->isOrphaned()) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
      }
    }
    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * Check if the entity is currently protected.
   *
   * @return bool
   *   TRUE if the entity is currently protected, FALSE otherwise.
   */
  public function isProtected() {
    return $this->getEmbargoedAccessManager()?->isProtected($this) ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->isNew()) {
      return;
    }
    // Make sure that we create new revisions whenever the status changes,
    // otherwise our logic to determine if a node has been manually unpublished
    // before in self::unpublishedManually() doesn't work properly.
    $original = $storage->loadUnchanged($this->id());
    if ($original instanceof ContentBase && $original->isPublished() != $this->isPublished()) {
      $this->setNewRevision();
    }
  }

  /**
   * Set the given node as the current context.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to set as the current context.
   */
  public function setContextNode(NodeInterface $node) {
    $this->contextNode = $node;
  }

  /**
   * Get the current context node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The context node if set.
   */
  public function getContextNode() {
    if (!$this->contextNode) {
      $section = $this->getCurrentSectionNode();
      if ($section && $this->isValidContextNode($section)) {
        $this->setContextNode($section);
      }
    }
    return $this->contextNode && $this->isValidContextNode($this->contextNode) ? $this->contextNode : NULL;
  }

  /**
   * Get the page title for a content node.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The page title.
   */
  public function getPageTitle() {
    if ($context_node = $this->getContextNode()) {
      return $context_node->label();
    }
    else {
      return $this->label();
    }
  }

  /**
   * Check if the given node is a valid context.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return bool
   *   TRUE if the given node is a valid context, FALSE otherwise.
   */
  public function isValidContextNode($node) {
    if ($node instanceof SectionNodeInterface) {
      return $this->isPartOfSection($node);
    }
    return FALSE;
  }

  /**
   * Check if this node is the main object on the page.
   *
   * @return bool
   *   TRUE if the current node is the main object on the page, FALSE
   *   otherwise.
   */
  public function isStandalonePage() {
    $document = $this->getCurrentDocumentNode();
    $section = $this->getCurrentSectionNode();
    if ($this instanceof Article && !$document && !$section) {
      return TRUE;
    }
    if ($this instanceof ContentBase && !$section) {
      return TRUE;
    }
  }

  /**
   * Check if the content is part of the given section.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section node.
   *
   * @return bool
   *   TRUE if the content is part of the given section, FALSE otherwise.
   */
  public function isPartOfSection(SectionNodeInterface $section) {
    $nodes = $this->getContentManager()->loadNodesForSection($section);
    return array_key_exists($this->id(), $nodes);
  }

  /**
   * Check if the content is orphaned.
   *
   * @return bool
   *   TRUE if the content is orphaned, FALSE otherwise.
   */
  public function isOrphaned() {
    if (!$this->hasField(OrphanedContentController::FIELD_NAME)) {
      return FALSE;
    }
    return $this->get(OrphanedContentController::FIELD_NAME)->value ?? FALSE;
  }

  /**
   * Check if the content is orphaned.
   *
   * @param bool $status
   *   TRUE if the content is orphaned, FALSE otherwise.
   */
  public function setOrphaned($status) {
    if (!$this->hasField(OrphanedContentController::FIELD_NAME)) {
      return $this;
    }
    return $this->set(OrphanedContentController::FIELD_NAME, $status);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // Views apparently uses 'revision' as a $rel when rendering linked titles.
    if (in_array($rel, ['canonical', 'revision']) && $context_node = $this->getContextNode()) {
      $context_url = $context_node->toUrl()->toString();
      $content_url = parent::toUrl($rel, ['absolute' => FALSE] + $options)->toString();
      $url = Url::fromUserInput($context_url . $content_url);
      // Prevent this being processed by the path alias manager.
      $url->setOption('alias', TRUE);
      // Set the custom path.
      $url->setOption('custom_path', $context_url . $content_url);
      return $url;
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function toLink($text = NULL, $rel = 'canonical', array $options = []) {
    if (!isset($text)) {
      // Use the short title as default.
      $text = $this->getShortTitle() ?? NULL;
    }
    return parent::toLink($text, $rel, $options);
  }

  /**
   * Get the short title for a document.
   */
  public function getShortTitle() {
    return $this->get('field_short_title')->value ?? NULL;
  }

  /**
   * Get the meta data for this article.
   *
   * @param bool $include_social
   *   Whether to include social icons in the metadata.
   *
   * @return array
   *   An array of metadata items.
   */
  public function getPageMetaData($include_social = TRUE) {
    $metadata = [];
    $metadata[] = [
      '#markup' => new TranslatableMarkup('Published on @date', [
        '@date' => $this->getDateFormatter()->format($this->getCreatedTime(), 'custom', 'j F Y'),
      ]),
    ];
    $tags = $this->getDisplayTags();
    if (!empty($tags)) {
      $metadata[] = [
        '#markup' => new TranslatableMarkup('Keywords @keywords', [
          '@keywords' => implode(', ', $tags),
        ]),
      ];
    }
    if ($this->isPublished() && $include_social) {
      $metadata[] = [
        '#theme' => 'social_links',
      ];
    }
    return $metadata;
  }

  /**
   * Get the tags for this content.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term objects.
   */
  public function getTags($include_context_tags = FALSE) {
    $field_tags = $this->get('field_tags');
    if (!$field_tags instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }
    // Get the tags.
    $tags = $field_tags->referencedEntities();

    // See if the context nodes tags should also be loaded.
    if ($include_context_tags) {
      $context_node = $this->getContextNode();
      // Merging the tags. This can lead to duplication in $tags.
      $tags = array_merge($tags, $context_node instanceof ContentBase ? $context_node->getTags() : []);
    }

    // Get the term ids to be able to re-key them to use the term id as key.
    $term_ids = array_map(function (TermInterface $term) {
      return $term->id();
    }, $tags);
    // There might be duplications in both $term_ids and $tags. But they should
    // be exactly equal, so this should not be an issue. According to the docs
    // for array_combine, if 2 keys are the same, the second one prevails.
    $tags = array_combine($term_ids, $tags);

    return $tags;
  }

  /**
   * Get the tags for display.
   *
   * @param int $limit
   *   How many tags to return at most.
   *
   * @return array
   *   An array of tag names.
   */
  public function getDisplayTags($limit = 6) {
    $cache_tags = [];

    // Get the tags.
    $tags = $this->getTags(TRUE);

    $structural_tags = $this->getStructuralTags();
    $structural_tag_ids = array_map(function ($term) {
      return $term->id();
    }, $structural_tags);

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) use (&$cache_tags, $structural_tag_ids) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $cache_tags = Cache::mergeTags($cache_tags, $tag->getCacheTags());
      return !in_array($tag->id(), $structural_tag_ids);
    });

    // Limit number of tags.
    if ($limit > 0 && count($tags) > $limit) {
      $tags = array_slice($tags, 0, $limit);
    }

    // Turn the array of objects into an array of names.
    $tag_names = array_map(function ($tag) {
      return $tag->label();
    }, $tags);
    return array_values($tag_names);
  }

  /**
   * Get the structural tags for an article.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term entities.
   */
  public function getStructuralTags() {
    // Get the tags.
    $tags = $this->getTags();

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $is_structural_tag = (bool) $tag->hasField('field_structural_tag') && $tag->get('field_structural_tag')?->value ?? FALSE;
      return $is_structural_tag;
    });

    return $tags;
  }

  /**
   * Get the content space.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The content space term.
   */
  public function getContentSpace() {
    if (!$this->hasField('field_content_space')) {
      return NULL;
    }
    return $this->get('field_content_space')?->entity ?? NULL;
  }

  /**
   * Get the node with the image to be displayed.
   *
   * @return \Drupal\ghi_sections\Entity\ImageNodeInterface
   *   A node object holding an image to be used as hero image.
   */
  public function getNodeWithHeroImage() {
    if (!$this->shouldDisplayHeroImage()) {
      return FALSE;
    }

    $inherit_section_image = $this->hasField('field_inherit_section_image') ? $this->get('field_inherit_section_image')->value : FALSE;
    if (($inherit_section_image === NULL || $inherit_section_image == 1) && $this->getContextNode() instanceof ImageNodeInterface) {
      /** @var \Drupal\ghi_sections\Entity\Section */
      $context_node = $this->getContextNode();
      if ($context_node instanceof ContentBase) {
        return $context_node->getNodeWithHeroImage();
      }
      return $context_node;
    }

    if (!$this->getImage()->isEmpty()) {
      return $this;
    }

    $context_node = $this->getContextNode();
    if ($context_node instanceof ContentBase && $context_node->shouldDisplayHeroImage()) {
      return $context_node->getNodeWithHeroImage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->get('field_image');
  }

  /**
   * Check if the current node can and should display a hero image.
   *
   * @return bool
   *   TRUE if a hero image is available and set to be displayed, FALSE
   *   otherwise.
   */
  public function shouldDisplayHeroImage() {
    if (!$this->hasField('field_image') || !$this->hasField('field_display_hero_image')) {
      return FALSE;
    }

    $context_node = $this->getContextNode();
    $inherit_section_image = $this->hasField('field_inherit_section_image') ? $this->get('field_inherit_section_image')->value : FALSE;
    if (($inherit_section_image === NULL || $inherit_section_image == 1) && $context_node instanceof ImageNodeInterface) {
      /** @var \Drupal\ghi_sections\Entity\ImageNodeInterface */
      $image_node = $this->getContextNode();
      return !$image_node->getImage()->isEmpty();
    }

    if ($this->getImage()->isEmpty()) {
      return FALSE;
    }
    $display_hero_image = $this->get('field_display_hero_image')->value;
    return $display_hero_image == 1 || $display_hero_image === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $context_node = $this->getContextNode();
    if ($context_node) {
      $cache_tags = Cache::mergeTags($cache_tags, $context_node->getCacheTags());
    }
    $cache_tags = Cache::mergeTags($cache_tags, $this->getContentSpace()?->getCacheTags() ?? []);
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    $cache_contexts = Cache::mergeContexts($cache_contexts, ['url.path']);
    return $cache_contexts;
  }

  /**
   * Get the content manager service for the current node.
   *
   * @return \Drupal\ghi_content\ContentManager\BaseContentManager
   *   The content manager service.
   */
  public function getContentManager() {
    /** @var \Drupal\ghi_content\ContentManager\ManagerFactory $manager_factory */
    $manager_factory = $this->getContentManagerFactory();
    return $manager_factory->getContentManager($this);
  }

  /**
   * Get the content manager factory.
   *
   * @return \Drupal\ghi_content\ContentManager\ManagerFactory
   *   The content manager factory.
   */
  protected static function getContentManagerFactory() {
    return \Drupal::service('ghi_content.manager.factory');
  }

  /**
   * Get the date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  protected static function getDateFormatter() {
    return \Drupal::service('date.formatter');
  }

  /**
   * Get the embargoed access manager service.
   *
   * @return \Drupal\ghi_embargoed_access\EmbargoedAccessManager|null
   *   The embargoed access manager service or NULL.
   */
  protected static function getEmbargoedAccessManager() {
    $service = 'ghi_embargoed_access.manager';
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Check if the node has been manually unpublished.
   *
   * @return bool
   *   TRUE if, and only if,
   */
  public function unpublishedManually() {
    if ($this->isPublished()) {
      return FALSE;
    }
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());
    if (!$storage instanceof NodeStorageInterface) {
      return FALSE;
    }

    $revision_ids = $storage->revisionIds($this);
    if (count($revision_ids) == 1) {
      // We give it the benefit of a doubt.
      return TRUE;
    }
    foreach ($revision_ids as $revision_id) {
      /** @var \Drupal\ghi_content\Entity\ContentBase $revision */
      $revision = $storage->loadRevision($revision_id);
      if (!$revision) {
        continue;
      }
      if ($revision->getRevisionId() == $this->getRevisionId()) {
        continue;
      }
      if ($revision->isPublished()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
