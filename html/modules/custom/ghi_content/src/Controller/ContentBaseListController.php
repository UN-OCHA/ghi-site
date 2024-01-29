<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Base controller for content lists.
 */
abstract class ContentBaseListController extends ControllerBase {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Public constructor.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * Access callback for document admin pages on sections.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessUpdate() {
    return AccessResult::allowedIf($this->canRunMigration());
  }

  /**
   * Get the migration id responsible for the current list.
   *
   * @return string
   *   The id of a migration.
   */
  abstract protected function getMigrationId();

  /**
   * Run the documents migrations.
   *
   * @param string $redirect
   *   The url to redirect to after the migration has finished.
   * @param array $tags
   *   Optional tags to filter the source data by.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  protected function runMigration($redirect, array $tags = NULL) {
    // @todo Make this work with more than a single migration. One way to do
    // this, would be to fetch all definitions and then filter by the source
    // plugin used (RemoteSourceGraphQL).
    $migration = $this->getMigration();
    if (empty($migration)) {
      return [
        '#markup' => $this->t('There was an error processing your request. Please contact an administrator.'),
      ];
    }
    if (!$this->canRunMigration()) {
      $this->messenger()->addWarning($this->t('The import is currently running. Please try again later.'));
      return new RedirectResponse($redirect);
    }
    $options = [
      'update' => 0,
    ];
    if ($tags !== NULL) {
      $options['configuration'] = ['source_tags' => $tags];
    }
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage(), $options);
    $executable->batchImport();
    batch_process($redirect);
    $batch = batch_get();
    $url = $batch['url'];
    return new RedirectResponse($url->toString());
  }

  /**
   * Get the migration object.
   *
   * @return \Drupal\migrate\Plugin\Migration|null
   *   The migration object responsible for the current list.
   */
  protected function getMigration() {
    return $this->migrationPluginManager->createInstance($this->getMigrationId());
  }

  /**
   * Check if the migration can run.
   *
   * @return bool
   *   TRUE if it can run, FALSE otherwise.
   */
  protected function canRunMigration() {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->getMigration();
    if (empty($migration)) {
      return FALSE;
    }
    // Check status of the migration dependencies.
    $dependencies = $migration->getMigrationDependencies();
    $dependency_busy = FALSE;
    foreach ($dependencies['required'] ?? [] as $required_dependency) {
      $_migration = $this->migrationPluginManager->createInstance($required_dependency);
      if (!$_migration || $_migration->getStatus() != Migration::STATUS_IDLE) {
        $dependency_busy = TRUE;
        break;
      }
    }
    return $migration->getStatus() == Migration::STATUS_IDLE && !$dependency_busy;
  }

}
