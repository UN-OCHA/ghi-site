<?php

namespace Drupal\ghi_base_objects\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectType;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller class for manual migration of base objects from teh HCP API.
 *
 * This assumes the presence of backend views for each base object type,
 * follwing this naming convention: view.base_objects.BASE_OBJECT_TYPE, for
 * example view.base_objects.plan. Access to the migration trigger is given
 * based on view access to the backend view page.
 */
class BaseObjectMigrateController extends ControllerBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
    );
  }

  /**
   * Access callback for article admin pages on sections.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectType|null $base_object_type
   *   An base object type to migrate.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(BaseObjectType $base_object_type = NULL) {
    if (!$base_object_type) {
      return AccessResult::forbidden();
    }
    return Url::fromRoute('view.base_objects.' . $base_object_type->id())->access(NULL, TRUE);
  }

  /**
   * Callback for updating base objects.
   *
   * This runs a migration in a batch process.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectType|null $base_object_type
   *   An base object type to migrate.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  public function updateBaseObjects(BaseObjectType $base_object_type = NULL) {
    $redirect_url = Url::fromRoute('view.base_objects.' . $base_object_type->id())->toString();
    return $this->runBaseObjectMigration($base_object_type, $redirect_url);
  }

  /**
   * Run the base objects migrations.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectType|null $base_object_type
   *   An base object type to migrate.
   * @param string $redirect
   *   The url to redirect to after the migration has finished.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  private function runBaseObjectMigration(BaseObjectType $base_object_type, $redirect) {
    // This assumes that there is a migration with the same id as the base
    // object type.
    $migration = $this->migrationPluginManager->createInstance($base_object_type->id());
    if (empty($migration)) {
      return [
        '#markup' => $this->t('There was an error processing your request. Please contact an administrator.'),
      ];
    }
    $options = [
      // 'force' => 1,
      'update' => 1,
    ];
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage(), $options);
    $executable->batchImport();
    batch_process($redirect);
    $batch = batch_get();
    $url = $batch['url'];
    return new RedirectResponse($url->toString());
  }

}
