<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks using a table for their configuration.
 */
interface ConfigurableTableBlockInterface {

  /**
   * Get the allowed item types for a block instance.
   *
   * @return array
   *   An array describing the allowed item types. The keys are the machine
   *   names of configuration item plugins. The value is an array with optional
   *   meta data information that is used by each item type.
   */
  public function getAllowedItemTypes();

  /**
   * Retrieve the custom context for a block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext();

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
  public function getItemTypePluginForColumn(array $column, ?array $context = NULL);

}
