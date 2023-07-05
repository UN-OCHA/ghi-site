<?php

namespace Drupal\ghi_subpages_custom\Plugin\SectionMenuItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_sections\Menu\OptionalSectionMenuPluginInterface;
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
class CustomSubpage extends SectionMenuPluginBase implements OptionalSectionMenuPluginInterface {

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
   * Get the document for the current menu item.
   *
   * @return \Drupal\ghi_content\Entity\Document|null
   *   The document node if found, or NULL otherwise.
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
    $parent = $node->getParentNode();
    if (!$parent) {
      return NULL;
    }
    if ($parent != $this->getSection()) {
      return NULL;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($form, FormStateInterface $form_state) {
    $options = $this->getNodeOptions();
    $form['node_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Custom page'),
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return !empty($this->getNodeOptions());
  }

  /**
   * Get the options for the node select.
   *
   * @return string[]
   *   An array with node labels as options, keyed by the document id.
   */
  private function getNodeOptions() {
    $section = $this->getSection();
    $nodes = $this->customSubpageManager->loadNodesForSection($section);
    $options = array_map(function (EntityCustomSubpage $node) {
      return $node->label();
    }, $nodes);

    /** @var \Drupal\ghi_sections\Field\SectionMenuItemList $menu_item_list */
    $menu_item_list = clone $this->sectionMenuStorage->getSectionMenuItems();
    $exclude_node_ids = [];
    foreach ($menu_item_list->getAll() as $menu_item) {
      $plugin = $menu_item->getPlugin();
      if (!$plugin instanceof self || !$plugin->getNode()) {
        continue;
      }
      if ($plugin->getSection()->id() == $section->id() && array_key_exists($plugin->getNode()->id(), $nodes)) {
        $data = $menu_item->toArray();
        $node_id = $data['configuration']['node_id'];
        $exclude_node_ids[$node_id] = $node_id;
      }
    }
    $options = array_diff_key($options, $exclude_node_ids);

    return $options;
  }

}
