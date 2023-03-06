<?php

namespace Drupal\ghi_base_objects\Migrate;

use Drupal\migrate_tools\MigrateBatchExecutable;

/**
 * Base object specific migrate executable class for batch migrations.
 *
 * This is only used when manually starting a migration for a specific base
 * object type via BaseObjectMigrateController.
 *
 * @see \Drupal\ghi_base_objects\Controller\BaseObjectMigrateController
 */
class BaseObjectMigrateBatchExecutable extends MigrateBatchExecutable {

  /**
   * {@inheritdoc}
   */
  protected function batchOperations(array $migrations, string $operation, array $options = []): array {
    $operations = parent::batchOperations($migrations, $operation, $options);
    foreach ($operations as &$_operation) {
      $migration_id = $_operation[1][0];
      $_operation[1][1]['configuration'] = [
        'source' => [
          'cache_prefix' => $migration_id,
          'cache_base_time' => \Drupal::time()->getRequestTime(),
        ],
      ];
    }
    return $operations;
  }

}
