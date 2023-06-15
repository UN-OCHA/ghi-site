<?php

namespace Drupal\hpc_api\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a migrate process plugin to format the date.
 *
 * @MigrateProcessPlugin(
 *  id = "hpc_api_migrate_format_date"
 * )
 */
class FormatDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return date('Y-m-d', strtotime($value));
  }

}
