<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\Controller\MoveBlockController;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Controller\MoveBlockController as RestrictedMoveBlockController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for layout builder blocks in GHI.
 *
 * Currently this only provides title callbacks for the add/update forms.
 *
 * @see Drupal\ghi_blocks\EventSubscriber\LayoutBuilderRouteSubscriber
 */
class LayoutBuilderBlockController extends ControllerBase implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ContainerInterface $container, BlockManagerInterface $plugin_manager) {
    $this->container = $container;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Get the title for the add block form.
   *
   * @param string $plugin_id
   *   The plugin id of the block plugin that is about to be added.
   *
   * @return string
   *   The admin label of the plugin.
   */
  public function getAddBlockFormTitle($plugin_id = NULL) {
    $defaul_title = $this->t('Configure block');
    if ($plugin_id === NULL) {
      return $defaul_title;
    }
    $plugin_definition = $this->pluginManager->getDefinition($plugin_id);
    return $plugin_definition['admin_label'];
  }

  /**
   * Get the title for the update block form.
   *
   * @param Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the block.
   * @param string $uuid
   *   The uuid of the block.
   *
   * @return string
   *   The admin label of the plugin.
   */
  public function getUpdateBlockFormTitle(SectionStorageInterface $section_storage, $delta = NULL, $uuid = NULL) {
    $defaul_title = $this->t('Configure block');
    if ($delta === NULL || $uuid === NULL) {
      return $defaul_title;
    }
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $plugin = $component->getPlugin();
    if (!$plugin instanceof GHIBlockBase) {
      return $defaul_title;
    }
    $plugin_definition = $plugin->getPluginDefinition();
    return $plugin_definition['admin_label'];
  }

  /**
   * React to an entity being updated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being updated.
   */
  public function updateEntity(EntityInterface $entity) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    if (!$section_storage) {
      return;
    }
    $existing_uuids = [];
    $sections = $section_storage->getSections();
    foreach ($sections as $section) {
      $component = $section->getComponents();
      foreach ($component as $uuid => $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof GHIBlockBase) {
          continue;
        }
        $plugin->postSave($entity, $uuid);
        $existing_uuids[] = $uuid;
      }
    }

    // Now check if the original version had blocks that have disappeard.
    $section_storage = $this->getSectionStorageForEntity($entity->original);
    if (!$section_storage) {
      return;
    }
    $sections = $section_storage->getSections();
    foreach ($sections as $section) {
      $component = $section->getComponents();
      foreach ($component as $uuid => $component) {
        if (in_array($uuid, $existing_uuids)) {
          continue;
        }
        $plugin = $component->getPlugin();
        if (!$plugin instanceof GHIBlockBase) {
          continue;
        }
        $plugin->postDelete($entity, $uuid);
      }
    }
  }

  /**
   * Moves a block to another region.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta_from
   *   The delta of the original section.
   * @param int $delta_to
   *   The delta of the destination section.
   * @param string $region_to
   *   The new region for this block.
   * @param string $block_uuid
   *   The UUID for this block.
   * @param string|null $preceding_block_uuid
   *   (optional) If provided, the UUID of the block to insert this block after.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function moveBlock(SectionStorageInterface $section_storage, int $delta_from, int $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    // Decide which controller to use.
    $controller = MoveBlockController::create($this->container);
    if ($this->moduleHandler()->moduleExists('layout_builder_restrictions')) {
      $controller = RestrictedMoveBlockController::create($this->container);
    }

    // Prepare our own response. With the exception of errors from the
    // layout_builder_restrictions module, it will be empty.
    $response = new AjaxResponse();

    // Call the controller build method to persist changes.
    $original_response = $controller->build($section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid);
    foreach ($original_response->getCommands() as $command) {
      if ($command['command'] == 'openDialog') {
        $response->addCommand(new OpenDialogCommand($command['selector'], $command['dialogOptions']['title'], $command['data']));
      }
    }
    return $response;
  }

}
