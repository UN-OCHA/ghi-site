<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\hpc_downloads\Interfaces\HPCDownloadContainerInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\page_manager\Entity\PageVariant;

/**
 * Helper functions for block handling, especially for custom extentions.
 *
 * One main concept used by the Data Links, Static Blocks and Pane Comments
 * add-on modules, is that of a storage id. The storage id is a combination of
 * a block plugin id and the block plugin uuid. That way it's possible to
 * address a specific plugin instance on the page and associate our custom
 * entities to that.
 */
class BlockHelper {

  use LayoutEntityHelperTrait;

  /**
   * Get the storage id for a block.
   */
  public static function getStorageId(HPCBlockBase $block) {
    $plugin_id = $block->getPluginId();
    $block_uuid = $block->getUuid();
    if (empty($block_uuid) || empty($plugin_id)) {
      return NULL;
    }
    return $plugin_id . ':' . $block_uuid;
  }

  /**
   * Get the plugin uuid from the storage id.
   *
   * @param string $storage_id
   *   The storage id for the block.
   */
  public static function getPluginUuidFromStorageId($storage_id) {
    $position = strrpos($storage_id, ':');
    $plugin_uuid = substr($storage_id, $position + 1);
    return $plugin_uuid;
  }

  /**
   * Get the plugin id from the storage id.
   *
   * @param string $storage_id
   *   The storage id for the block.
   */
  public static function getPluginIdFromStorageId($storage_id) {
    $position = strrpos($storage_id, ':');
    $plugin_id = substr($storage_id, 0, $position);
    return $plugin_id;
  }

  /**
   * Load a plugin definition by the storage id.
   *
   * @param string $storage_id
   *   The storage id for the block.
   *
   * @return array
   *   The plugin definition array.
   */
  public static function getPluginDefinitionFromStorageId($storage_id) {
    $plugin_id = self::getPluginIdFromStorageId($storage_id);
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_definition = $block_manager->getDefinition($plugin_id);
    return $plugin_definition;
  }

  /**
   * Get a block instance based on the given context.
   *
   * Needs a uri for the page context, a plugin id and the block uuid.
   *
   * @param string $uri
   *   The URI where the block should be found.
   * @param string $plugin_id
   *   The plugin id of the block.
   * @param string $block_uuid
   *   The UUID of the block instance.
   *
   * @return \Drupal\hpc_common\Plugin\HPCBlockBase|null
   *   The block instances if it has been found.
   */
  public static function getBlockInstance($uri, $plugin_id, $block_uuid) {
    if (empty($uri)) {
      return NULL;
    }
    /** @var \Drupal\Core\Routing\Router $router */
    $router = \Drupal::service('router.no_access_checks');
    try {
      $page_parameters = $router->match($uri);
    }
    catch (\Exception $e) {
      return NULL;
    }

    $block = NULL;
    // We have 2 cases where we support our blocks:
    // 1. Page manager pages, which might also contain a content container with
    //    more elements defined in it.
    // 2. Node views that are build by layout_builder.
    if (!empty($page_parameters['_page_manager_page']) && !empty($page_parameters['_page_manager_page_variant'])) {
      // So this is a page manager page, so we get the page variant object and
      // extract the block from there.
      /** @var \Drupal\page_manager\Entity\PageVariant $page_variant */
      $page_variant = $page_parameters['_page_manager_page_variant'];
      $block = self::getBlockInstanceFromPageVariant($page_variant, $plugin_id, $block_uuid);
    }
    elseif (!empty($page_parameters['node'])) {
      // So this is a node view. At the moment this can only be a plan node,
      // but let's plan ahead and support all bundles.
      $entity = $page_parameters['node'];
      $block = self::getBlockInstanceFromEntity($entity, $plugin_id, $block_uuid);
    }

    if (!$block || !$block instanceof HPCBlockBase) {
      return NULL;
    }

    // Now add in the available context values.
    foreach (array_keys($block->getContextDefinitions()) as $context_key) {
      if (empty($page_parameters[$context_key])) {
        continue;
      }
      // Overwrite the existing context value if there is any.
      $block->setContextValue($context_key, $page_parameters[$context_key]);
    }

    // Now see if there are field contexts to setup too.
    $block->injectFieldContexts();

    // Set the page (node or page manager identifier) from the current page
    // parameters, so that the block knows more about it's context.
    // @see HPCBlockBase::setPage()
    $block->setPage($page_parameters);
    $block->setCurrentUri($uri);

    return $block;
  }

  /**
   * Get a block instance from a page variant.
   *
   * @param \Drupal\page_manager\Entity\PageVariant $page_variant
   *   The page variant object.
   * @param string $plugin_id
   *   The plugin id of the block.
   * @param string $block_uuid
   *   The uuid of the block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   A block plugin object or NULL.
   */
  private static function getBlockInstanceFromPageVariant(PageVariant $page_variant, $plugin_id, $block_uuid) {
    // We load the variant, get the block areas (called collections), look at
    // the configuration of section and search in the blocks that are
    // configured for this page until we find the block that matches plugin id
    // and block uuid. Then we use the configuration for that block on that
    // page to create an instance of that block using the block manager.
    $plugin_collection = $page_variant->getPluginCollections();
    if (empty($plugin_collection)) {
      return NULL;
    }
    $layout_builder_variant = $plugin_collection['variant_settings']->get('layout_builder');
    if (!$layout_builder_variant) {
      return NULL;
    }
    $plugin_configuration = $layout_builder_variant->getConfiguration();
    if (empty($plugin_configuration['sections'])) {
      return NULL;
    }
    /** @var \Drupal\layout_builder\Section[] $sections */
    $sections = $plugin_configuration['sections'];
    $component = self::getComponentFromLayoutBuilderSections($sections, $plugin_id, $block_uuid);
    if ($component) {
      return self::instantiateComponentPlugin($component);
    }

    // Otherwise we loop over all components to see if we find one that
    // implements HPCDownloadContainer interface.
    foreach ($sections as $section) {
      $components = $section->getComponents();
      if (empty($components)) {
        continue;
      }
      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof HPCDownloadContainerInterface) {
          continue;
        }

        if ($block_plugin = $plugin->findContainedPlugin($plugin_id, $block_uuid)) {
          return $block_plugin;
        }
      }
    }

    return NULL;
  }

  /**
   * Get a block instance from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string $plugin_id
   *   The plugin id of the block.
   * @param string $block_uuid
   *   The uuid of the block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   A block plugin object or NULL.
   */
  public static function getBlockInstanceFromEntity(ContentEntityInterface $entity, $plugin_id, $block_uuid) {
    $entity = is_object($entity) ? $entity : Node::load($entity);
    $bundle = $entity->bundle();

    // We support 2 versions here:
    // 1. Overridden nodes that use a custom layout.
    // 2. Nodes using the default layout that is set on the nodes manage
    //    display page.
    //
    // First see if this is an overridden node, in which case the node has a
    // field named 'layout_builder__layout'.
    // @todo This should ideally use LayoutEntityHelperTrait::getEntitySections
    // via a service.
    $layout_builder = $entity->hasField(OverridesSectionStorage::FIELD_NAME) ? $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue() : NULL;
    $sections = [];
    if ($layout_builder) {
      // Extract the nested sections.
      $sections = array_map(function ($section) {
        return $section['section'];
      }, $layout_builder);
    }
    if (empty($sections)) {
      // If we don't have section at this point, we try the default display.
      // That is more complicated, we need to get the section storage
      // service, extract some context from the route and use that to load
      // the defaults display and git it's sections.
      /** @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage */
      $section_storage = \Drupal::service('plugin.manager.layout_builder.section_storage');
      $contexts = $section_storage->loadEmpty('defaults')->deriveContextsFromRoute('node.' . $bundle . '.default', [], '', []);
      $display = $section_storage->load('defaults', $contexts);
      $sections = $display->getSections();
    }
    if (empty($sections)) {
      return NULL;
    }
    /** @var \Drupal\layout_builder\Section[] $sections */
    $component = self::getComponentFromLayoutBuilderSections($sections, $plugin_id, $block_uuid);
    if (!$component) {
      return NULL;
    }
    return self::instantiateComponentPlugin($component);
  }

  /**
   * Instantiate the plugin for the given section component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The component of the block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   A block plugin object or NULL.
   */
  private static function instantiateComponentPlugin(SectionComponent $component) {
    /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
    $plugin = $component->getPlugin();
    $block_config = $plugin->getConfiguration();
    $block_config['uuid'] = $component->getUuid();
    return self::getBlockManager()->createInstance($component->getPluginId(), $block_config);
  }

  /**
   * Get a component from a section list.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of sections.
   * @param string $plugin_id
   *   The plugin id of the block.
   * @param string $block_uuid
   *   The uuid of the block.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   A section component object or NULL.
   */
  private static function getComponentFromLayoutBuilderSections(array $sections, $plugin_id, $block_uuid) {
    if (empty($sections)) {
      return NULL;
    }
    // If we have sections, search in the components until we find a block
    // with a matching plugin id and block uuid.
    // Then we use the configuration for that block on that page to create
    // an instance of that block using the block manager.
    foreach ($sections as $section) {
      $components = $section->getComponents();
      if (empty($components)) {
        continue;
      }
      foreach ($components as $component) {
        if ($component->getPluginId() != $plugin_id) {
          continue;
        }
        if ($component->getUuid() != $block_uuid) {
          continue;
        }
        return $component;
      }
    }
  }

  /**
   * Get the block manager service.
   *
   * @return \Drupal\Core\Block\BlockManager
   *   The block manager service.
   */
  private static function getBlockManager() {
    return \Drupal::service('plugin.manager.block');
  }

}
