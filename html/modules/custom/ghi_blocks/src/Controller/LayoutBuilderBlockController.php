<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
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
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(BlockManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
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

}
