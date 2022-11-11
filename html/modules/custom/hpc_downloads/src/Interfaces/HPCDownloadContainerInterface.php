<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface for a container plugin, which contains downloadable plugins.
 */
interface HPCDownloadContainerInterface {

  /**
   * Find a plugin contained inside this plugin.
   *
   * @param string $plugin_id
   *   The plugin id to look for.
   * @param string $block_uuid
   *   The blocks uuid to look form.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The block plugin if found or NULL.
   */
  public function findContainedPlugin($plugin_id, $block_uuid);

}
