<?php

namespace Drupal\ghi_form_elements\Traits;

use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Helper trait for classes using a configuration container.
 */
trait ConfigurationContainerTrait {

  use SimpleCacheTrait;

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
   * Retrieve the valid configured items as plugin instances from an item store.
   *
   * @param array $item_store
   *   The storage for configured items. This should be in most cases a part of
   *   the block configuration array.
   * @param array $context
   *   An array of context objects.
   *
   * @return \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface[]
   *   An array of configuration container plugin instances.
   */
  public function getConfiguredItemPlugins(array $item_store = NULL, array $context) {
    if (empty($item_store)) {
      return NULL;
    }
    $items = $this->getConfiguredItems($item_store);
    if (empty($items)) {
      return NULL;
    }
    $plugins = [];
    foreach ($items as $key => $item) {
      $plugins[$key] = $this->getItemTypePluginForColumn($item, $context);
    }
    return $plugins;
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
    // Check if this item is allowed. Do that lazy as this is called often.
    $allowed_items = &drupal_static(($this->getUuid() ?? rand()) . '_' . __FUNCTION__ . '_allowed_items', NULL);
    if ($allowed_items === NULL) {
      $allowed_items = $this->getAllowedItemTypes();
    }
    if ($context === NULL) {
      $context = $this->getBlockContext();
    }
    // Get an instance of the item type plugin for this column, set it's
    // config and the context.
    $item_type_plugin = $allowed_items[$column['item_type']]['item_type_base'] ?? $column['item_type'];

    // This is called very often, don't repeat it for identical configuration.
    $cache_key = $this->getCacheKey($column);
    /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface[] $item_types */
    $item_types = &drupal_static($this->getUuid() . '_' . __FUNCTION__ . '_item_types', []);
    if (!$this->getUuid() || !array_key_exists($cache_key, $item_types)) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getConfigurationContainerItemManager()->createInstance($item_type_plugin, $allowed_items[$column['item_type']]);
      $item_type->setConfig($column['config'] ?? []);
      $item_types[$cache_key] = $item_type;
    }
    // But make sure that every instance has fresh context.
    $item_types[$cache_key]->setContext($context);
    return $item_types[$cache_key];
  }

  /**
   * Build a table header based on the given columns.
   *
   * @param array $columns
   *   An array of valid item configurations.
   *
   * @return array
   *   An array of header items, suitable to be used with theme_table.
   */
  public function buildTableHeader(array $columns) {
    $header = [];
    foreach ($columns as $column) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($column);
      $header[] = [
        'data' => $item_type->getLabel(),
        'data-sort-type' => $item_type::SORT_TYPE,
        'data-sort-order' => count($header) == 0 ? 'ASC' : '',
        'data-column-type' => $item_type->getColumnType(),
      ];
    }
    return $header;
  }

}
