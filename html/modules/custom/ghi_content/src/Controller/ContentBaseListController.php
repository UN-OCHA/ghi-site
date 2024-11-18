<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->time = $container->get('datetime.time');
    return $instance;
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
  protected function runMigration($redirect, ?array $tags = NULL) {
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
    $source_plugin = $migration->getSourcePlugin();
    if ($source_plugin instanceof RemoteSourceGraphQL) {
      $source_plugin->setSourceTags($tags ?? []);
      // Not quite sure why this is necessary, as it should be done already by
      // RemoteSourceGraphQL::preImport().
      $source_plugin->setCacheBaseTime($this->time->getRequestTime());
    }
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage());
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

  /**
   * Get the section node for the given node if any.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to retrieve the section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   A section node object if any can be found.
   */
  protected function getSectionNode($node) {
    if ($node instanceof SectionNodeInterface) {
      return $node;
    }
    if ($node instanceof SubpageNodeInterface) {
      return $node->getParentBaseNode();
    }
  }

}
