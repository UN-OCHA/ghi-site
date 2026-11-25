<?php

namespace Drupal\ghi_base_objects\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
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
   * PHP error types that can terminate a batch request before normal cleanup.
   */
  private const FATAL_ERROR_TYPES = [
    E_ERROR,
    E_PARSE,
    E_CORE_ERROR,
    E_COMPILE_ERROR,
    E_USER_ERROR,
    E_RECOVERABLE_ERROR,
  ];

  /**
   * {@inheritdoc}
   */
  public static function batchProcessImport(string $migration_id, array $options, &$context): void {
    $operation_completed = FALSE;
    // Fatal errors bypass the catch block. Capture the flag by reference so
    // the shutdown handler can distinguish interrupted and completed imports.
    register_shutdown_function(static function () use ($migration_id, &$operation_completed): void {
      static::recoverFromShutdown($migration_id, $operation_completed, error_get_last());
    });

    try {
      static::processBatchOperation($migration_id, $options, $context);
      $operation_completed = TRUE;
    }
    catch (\Throwable $exception) {
      static::resetInterruptedImportStatus($migration_id, fatal_error: FALSE);
      $operation_completed = TRUE;
      throw $exception;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function batchOperations(array $migrations, string $operation, array $options = []): array {
    $operations = parent::batchOperations($migrations, $operation, $options);
    foreach ($operations as &$batch_operation) {
      $migration_id = $batch_operation[1][0];
      $batch_operation[1][1]['configuration'] = [
        'source' => [
          'cache_prefix' => $migration_id,
          'cache_base_time' => \Drupal::time()->getRequestTime(),
        ],
      ];
    }
    unset($batch_operation);
    return $operations;
  }

  /**
   * Run the underlying batch operation.
   *
   * This separate method allows tests to simulate an interrupted import
   * without running a real migration.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param array $options
   *   The batch executable options.
   * @param array|\DrushBatchContext $context
   *   The batch context.
   */
  protected static function processBatchOperation(string $migration_id, array $options, &$context): void {
    parent::batchProcessImport($migration_id, $options, $context);
  }

  /**
   * Reset a migration left importing by a fatal batch request.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param bool $operation_completed
   *   Whether the batch operation completed normally.
   * @param array|null $error
   *   The last PHP error.
   */
  protected static function recoverFromShutdown(string $migration_id, bool $operation_completed, ?array $error): void {
    if ($operation_completed || !static::isFatalError($error)) {
      return;
    }
    static::resetInterruptedImportStatus($migration_id, fatal_error: TRUE);
  }

  /**
   * Return a migration to idle when an interrupted import left it busy.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param bool $fatal_error
   *   Whether a fatal PHP error interrupted the import.
   */
  protected static function resetInterruptedImportStatus(string $migration_id, bool $fatal_error): void {
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
    if (!$migration instanceof MigrationInterface || $migration->getStatus() !== MigrationInterface::STATUS_IMPORTING) {
      return;
    }

    $migration->setStatus(MigrationInterface::STATUS_IDLE);
    $message = $fatal_error
      ? 'Reset migration @migration_id to idle after a fatal batch error.'
      : 'Reset migration @migration_id to idle after an unexpected exception.';
    \Drupal::logger('ghi_base_objects')->warning($message, [
      '@migration_id' => $migration_id,
    ]);
  }

  /**
   * Determine whether an error can terminate the current request.
   *
   * @param array|null $error
   *   The PHP error details.
   *
   * @return bool
   *   TRUE if this is a fatal error, FALSE otherwise.
   */
  protected static function isFatalError(?array $error): bool {
    return $error !== NULL && in_array($error['type'] ?? NULL, self::FATAL_ERROR_TYPES, TRUE);
  }

}
