<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionManager;

/**
 * A storage class for the section menu.
 */
class SectionMenuStorage {

  use StringTranslationTrait;

  const FIELD_NAME = 'section_menu';

  /**
   * The section entity.
   *
   * @var \Drupal\ghi_sections\Entity\SectionNodeInterface
   */
  protected $section;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * The section menu item manager.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuPluginManager
   */
  protected $sectionMenuPluginManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Construct a section menu storage object.
   *
   * @param \Drupal\ghi_sections\SectionManager $section_manager
   *   The section manager.
   * @param SectionMenuPluginManager $section_menu_plugin_manager
   *   The section menu plugin manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   Optional: The section node to be used for the storage. If not set
   *   directly during construction, this class will try to retrieve the
   *   current section from the current route match.
   */
  public function __construct(SectionManager $section_manager, SectionMenuPluginManager $section_menu_plugin_manager, RouteMatchInterface $route_match, SectionNodeInterface $section = NULL) {
    $this->sectionManager = $section_manager;
    $this->sectionMenuPluginManager = $section_menu_plugin_manager;
    $this->routeMatch = $route_match;
    $this->section = $section;
  }

  /**
   * Get the section.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node object or NULL if not available.
   */
  public function getSection() {
    if ($this->section === NULL) {
      // If we don't have a section entity at this point, get it from the
      // current route.
      $node = $this->routeMatch->getParameter('node') ?? NULL;
      if ($node && $section = $this->sectionManager->getCurrentSection($node)) {
        $this->section = $section;
      }
    }
    return $this->section;
  }

  /**
   * Get the menu item list.
   *
   * @return \Drupal\ghi_sections\Field\SectionMenuItemList
   *   A menu item list for the current section.
   */
  public function getSectionMenuItems() {
    /** @var \Drupal\ghi_sections\Field\SectionMenuItemList $menu_item_list */
    $menu_item_list = $this->getSection()?->get(self::FIELD_NAME) ?? NULL;
    if ($menu_item_list->isEmpty()) {
      $menu_item_list->setMenuItems($this->getDefaultMenuItems());
    }
    return $menu_item_list;
  }

  /**
   * Get the default section menu items.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuItemInterface[]
   *   An array with the available section menu items for the given section.
   */
  public function getDefaultMenuItems() {
    $section = $this->getSection();
    $menu_items = [];
    $definitions = $this->sectionMenuPluginManager->getDefinitions();
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);
    foreach ($definitions as $plugin_id => $definition) {
      /** @var \Drupal\ghi_sections\Menu\SectionMenuPluginInterface $plugin */
      $plugin = $this->sectionMenuPluginManager->createInstance($plugin_id, [
        'section' => $section->id(),
      ]);
      $menu_item = $plugin->getItem();
      if ($menu_item instanceof SectionMenuItemInterface) {
        $menu_items[] = $menu_item;
      }
    }
    return $menu_items;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $this->getSection()->isSyncing(TRUE);
    return $this->getSection()->save();
  }

  /**
   * Add the section menu field to the given bundle.
   *
   * @param string $bundle
   *   The node bundle.
   */
  public function addSectionMenuField($bundle) {
    $entity_type_id = 'node';
    $field_name = self::FIELD_NAME;
    $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
    if (!$field) {
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if (!$field_storage) {
        $field_storage = FieldStorageConfig::create([
          'entity_type' => $entity_type_id,
          'field_name' => $field_name,
          'type' => 'section_menu',
          'locked' => TRUE,
        ]);
        $field_storage->setTranslatable(FALSE);
        $field_storage->save();
      }

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => $this->t('Section menu'),
      ]);
      $field->setTranslatable(FALSE);
      $field->save();
    }
  }

  /**
   * Remove the section menu field from the given bundle.
   *
   * @param string $bundle
   *   The node bundle.
   */
  public function removeSectionMenuField($bundle) {
    $entity_type_id = 'node';
    $field_name = self::FIELD_NAME;
    if ($field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name)) {
      $field->delete();
    }
  }

}
