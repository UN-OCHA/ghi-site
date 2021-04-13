<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;

use Drupal\hpc_common\Plugin\HPCBlockBase;

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
   * @return \Drupal\hpc_common\Plugin\HPCBlockBase
   *   The block instances if it has been found.
   */
  public static function getBlockInstance($uri, $plugin_id, $block_uuid) {
    $router = \Drupal::service('router.no_access_checks');
    $page_parameters = $router->match($uri);

    $block = NULL;
    $block_manager = \Drupal::service('plugin.manager.block');

    // We have 2 cases where we support to our blocks:
    // 1. Page manager pages
    // 2. Node views that are build by layout_builder.
    if (!empty($page_parameters['page_manager_page']) && !empty($page_parameters['page_manager_page_variant'])) {
      // So this is a page manager page. We load the variant, get the block
      // areas (called collections), look at the configuration of every panel
      // and search in the blocks that are configured for this page until we
      // find the block that matches plugin id and block uuid.
      // Then we use the configuration for that block on that page to create an
      // instance of that block using the block manager.
      $page_variant = $page_parameters['page_manager_page_variant'];
      $plugin_collection = $page_variant->getPluginCollections();
      if (empty($plugin_collection)) {
        return NULL;
      }
      $panels_variant = $plugin_collection['variant_settings']->get('panels_variant');
      if (!$panels_variant) {
        return NULL;
      }
      $plugin_configuration = $panels_variant->getConfiguration();
      if (empty($plugin_configuration['blocks'])) {
        return NULL;
      }
      $blocks = $plugin_configuration['blocks'];
      if (empty($blocks[$block_uuid])) {
        return NULL;
      }
      // Get the config.
      $block_config = $blocks[$block_uuid];
      // Create the instance.
      $block = $block_manager->createInstance($plugin_id, $block_config);
    }
    elseif (!empty($page_parameters['node'])) {
      // So this is a node view. At the moment this can only be a plan node,
      // but let's plan ahead and support all bundles.
      $entity = $page_parameters['node'];
      $bundle = $entity->bundle();

      // We support 2 versions here:
      // 1. Overridden nodes that use a custom layout.
      // 2. Nodes using the default layout that is set on the nodes manage
      //    display page.
      //
      // First see if this is an overridden node, in which case the node has a
      // field named 'layout_builder__layout'.
      $layout_builder = $entity->hasField('layout_builder__layout') ? $entity->get('layout_builder__layout')->getValue() : NULL;
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
        $section_storage = \Drupal::service('plugin.manager.layout_builder.section_storage');
        $contexts = $section_storage->loadEmpty('defaults')->deriveContextsFromRoute('node.' . $bundle . '.default', [], '', []);
        $display = $section_storage->load('defaults', $contexts);
        $sections = $display->getSections();
      }
      if ($sections) {
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
            $block_config = $component->getPlugin()->getConfiguration();
            $block = $block_manager->createInstance($plugin_id, $block_config);
            break(2);
          }
        }
      }

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
    $plugin_definition = $block->getPluginDefinition();
    if (!empty($plugin_definition['field_context_mapping'])) {
      foreach ($plugin_definition['field_context_mapping'] as $context_key => $context_definition) {
        $context_type = is_string($context_definition) ? 'integer' : $context_definition['type'];
        if (empty($page_parameters[$context_key])) {
          continue;
        }
        if (empty($plugin_definition['context_definitions'][$context_key])) {
          // Create a new context.
          $context_definition = new ContextDefinition($context_type, 'test', FALSE);
          $context = new Context($context_definition, $page_parameters[$context_key]);
          $block->setContext($context_key, $context);
        }
        else {
          // Overwrite the existing context value if there is any.
          $block->setContextValue($context_key, $page_parameters[$context_key]);
        }
      }
    }

    // Set the page (node or page manager identifier) from the current page
    // parameters, so that the block knows more about it's context.
    // @see HPCBlockBase::setPage()
    $block->setPage($page_parameters);
    $block->setCurrentUri($uri);

    return $block;
  }

}
