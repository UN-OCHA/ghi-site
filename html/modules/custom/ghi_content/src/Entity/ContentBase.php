<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_sections\Entity\ImageNodeInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for section nodes.
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
    if ($this->contextNode) {
      return $this->contextNode;
    }
    if ($this instanceof ContentBase && $section = $this->getCurrentSectionNode()) {
      $this->contextNode = $section;
      return $this->contextNode;
    }
    if ($this instanceof Article && $document = $this->getCurrentDocumentNode()) {
      $this->contextNode = $document;
      return $this->contextNode;
    }
    return NULL;
  }

  /**
   * Check if this node is the main object on the page.
   *
   * @return bool
   *   TRUE if the current node is the main object on the page, FALSE
   *   otherwise.
   */
  public function isStandalonePage() {
    if ($this instanceof Article && !$this->getCurrentDocumentNode()) {
      return TRUE;
    }
    if ($this instanceof ContentBase && !$this->getCurrentSectionNode()) {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical' && $context_node = $this->getContextNode()) {
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
   * Get the meta data for this article.
   *
   * @return array
   *   An array of metadata items.
   */
  abstract public function getPageMetaData();

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
    $tags = $this->get('field_tags')->referencedEntities();
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

    // And build the render array.
    return $tag_names;
  }

  /**
   * Get the structural tags for an article.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term entities.
   */
  public function getStructuralTags() {
    // Get the tags.
    $tags = $this->get('field_tags')->referencedEntities();

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $is_structural_tag = (bool) $tag->get('field_structural_tag')?->value ?? FALSE;
      return $is_structural_tag;
    });

    return $tags;
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
    if (($inherit_section_image === NULL || $inherit_section_image == 1) && $context_node instanceof Section) {
      /** @var \Drupal\ghi_sections\Entity\Section */
      $section = $this->getContextNode();
      return !$section->getImage()->isEmpty();
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

}
