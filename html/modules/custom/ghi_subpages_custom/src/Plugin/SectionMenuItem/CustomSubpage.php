<?php

namespace Drupal\ghi_subpages_custom\Plugin\SectionMenuItem;

use Drupal\ghi_sections\Menu\SectionMenuItem;
use Drupal\ghi_sections\Menu\SectionMenuPluginBase;
use Drupal\ghi_sections\MenuItemType\SectionNode;
use Drupal\ghi_subpages_custom\Entity\CustomSubpage as EntityCustomSubpage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a document subpages item for section menus.
 *
 * @SectionMenuPlugin(
 *   id = "custom_subpage",
 *   label = @Translation("Custom subpage"),
 *   description = @Translation("This item links to a custom subpage of a section."),
 *   weight = 4,
 * )
 */
class CustomSubpage extends SectionMenuPluginBase {

  /**
   * The subpage manager.
   *
   * @var \Drupal\ghi_subpages_custom\CustomSubpageManager
   */
  protected $customSubpageManager;

  /**
   * The node type.
   *
   * @var string
   */
  protected $nodeId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->customSubpageManager = $container->get('ghi_subpages_custom.manager');
    $instance->nodeId = $configuration['node_id'] ?? NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->getNode()?->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    $node = $this->getNode();
    if (!$node) {
      return NULL;
    }
    $item = new SectionMenuItem($this->getPluginId(), $this->getSection()->id(), $node->label());
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    $node = $this->getNode();
    $item = $this->getItem();
    return new SectionNode($item->getLabel(), $node);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getNode()?->isPublished();
  }

  /**
   * Get the node id for this plugin.
   *
   * @return int
   *   A node id.
   */
  public function getNodeId() {
    return $this->nodeId;
  }

  /**
   * Get the custom subpage for the current menu item.
   *
   * @return \Drupal\ghi_subpages_custom\Entity\CustomSubpage|null
   *   The custom subpage node if found, or NULL otherwise.
   */
  private function getNode() {
    if (!$this->nodeId) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($this->nodeId);
    return $node instanceof EntityCustomSubpage ? $node : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    $node = $this->getNode();
    if (!$node instanceof EntityCustomSubpage) {
      return NULL;
    }
    $parent = $node->getParentBaseNode();
    if (!$parent) {
      return NULL;
    }
    if ($parent != $this->getSection()) {
      return NULL;
    }
    return TRUE;
  }

}
