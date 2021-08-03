<?php

namespace Drupal\ghi_form_elements\Traits;

/**
 * Helper trait for classes using a configuration container.
 */
trait ConfigurationContainerTrait {

  /**
   * Get the allowed item types for a block instance.
   *
   * @return array
   *   An array describing the allowed item types. The keys are the machine
   *   names of configuration item plugins. The value is an array with optional
   *   meta data information that is used by each item type.
   */
  abstract public function getAllowedItemTypes();

  /**
   * Retrieve the custom context for a block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  abstract public function getBlockContext();

  /**
   * Get the configuration container item manager.
   *
   * @return \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   *   The configuration container item manager class.
   */
  protected function getConfigurationContainerItemManager() {
    if (!property_exists($this, 'configurationContainerItemManager')) {
      throw new \Exception('Missing property configurationContainerItemManager on class %s. Either add this property or overwrite the method getConfigurationContainerItemManager() to provide the configuration container item manager class. ', get_called_class());
    }
    return $this->configurationContainerItemManager;
  }

  /**
   * Retrieve the valid configured items from an item store.
   *
   * @param array $item_store
   *   The storage for configured items. This should be in most cases a part of
   *   the block configuration array.
   *
   * @return array
   *   An array of valid item configurations.
   */
  public function getConfiguredItems(array $item_store = NULL) {
    if (empty($item_store)) {
      return NULL;
    }

    $allowed_items = $this->getAllowedItemTypes();
    $items = array_filter($item_store, function ($item) use ($allowed_items) {
      return is_array($item) && array_key_exists('item_type', $item) && array_key_exists($item['item_type'], $allowed_items);
    });
    return $items;
  }

  /**
   * Get the item type plugin responsible for the given column.
   *
   * @param array $column
   *   A column array.
   * @param array $context
   *   An array of context objects.
   *
   * @return \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface
   *   The item type plugin.
   */
  public function getItemTypePluginForColumn(array $column, array $context = NULL) {
    // Get an instance of the item type plugin for this column, set it's
    // config and the context.
    $allowed_items = $this->getAllowedItemTypes();
    if ($context === NULL) {
      $context = $this->getBlockContext();
    }
    /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
    $item_type = $this->getConfigurationContainerItemManager()->createInstance($column['item_type'], $allowed_items[$column['item_type']]);
    $item_type->setConfig($column['config']);
    $item_type->setContext($context);
    return $item_type;
  }

}
