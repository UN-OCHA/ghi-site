<?php

namespace Drupal\hpc_api\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Extracts category IDs for a specific category group.
 *
 * Example:
 *
 * @code
 * process:
 *   bar:
 *     plugin: extract_category_id
 *     category_group: planType
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "extract_category_id",
 *   handle_multiples = TRUE
 * )
 */
class ExtractCategoryId extends ProcessPluginBase {

  /**
   * The category group for which IDs should be extracted.
   *
   * @var string
   */
  private $categoryGroup;

  /**
   * Constructs a Transliteration plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->categoryGroup = $configuration['category_group'];
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $raw_data = $row->getSource()['raw'];
    $categories = $raw_data['categories'];
    $category_group = $this->categoryGroup;
    $matching_categories = array_filter($categories, function ($item) use ($category_group) {
      return $item['group'] == $category_group;
    });
    if (!empty($matching_categories)) {
      // There should really be only one.
      $category = reset($matching_categories);
      return $category['id'];
    }
    return NULL;
  }

}
