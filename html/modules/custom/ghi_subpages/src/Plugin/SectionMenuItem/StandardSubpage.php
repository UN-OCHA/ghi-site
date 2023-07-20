<?php

namespace Drupal\ghi_subpages\Plugin\SectionMenuItem;

use Drupal\ghi_sections\Menu\SectionMenuItem;
use Drupal\ghi_sections\Menu\SectionMenuPluginBase;
use Drupal\ghi_sections\MenuItemType\SectionNode;
use Drupal\ghi_subpages\Entity\SubpageNode;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a standard subpage item for section menus.
 *
 * @SectionMenuPlugin(
 *   id = "standard_subpage",
 *   label = @Translation("Standard subpage"),
 *   description = @Translation("This item links to a standard subpage of a section."),
 *   weight = 0,
 *   deriver = "Drupal\ghi_subpages\Plugin\Derivative\StandardSubpageSectionMenuItemDeriver",
 * )
 */
class StandardSubpage extends SectionMenuPluginBase {

  /**
   * The node type.
   *
   * @var string
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->nodeType = $plugin_definition['node_type'];
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Subpage');
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    $subpage = $this->getSubpage();
    if (!$subpage) {
      return NULL;
    }
    $item = new SectionMenuItem($this->getPluginId(), $this->getSection()->id(), $subpage->label());
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    $subpage = $this->getSubpage();
    if (!$subpage) {
      return NULL;
    }
    $item = $this->getItem();
    return new SectionNode($item->getLabel(), $subpage);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getSubpage()?->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return $this->getSubpage() instanceof SubpageNodeInterface;
  }

  /**
   * Get the subpage for the current menu item.
   *
   * @return \Drupal\ghi_subpages\Entity\SubpageNodeInterface|null
   *   The subpage node if found, or NULL otherwise.
   */
  private function getSubpage() {
    $section = $this->getSection();
    if (!$section) {
      return NULL;
    }
    $subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $this->nodeType,
      'field_entity_reference' => $section->id(),
    ]);
    $subpage = count($subpages) == 1 ? reset($subpages) : NULL;
    return $subpage instanceof SubpageNode ? $subpage : NULL;
  }

}
