<?php

namespace Drupal\hpc_common\Plugin;

/**
 * Interface for HPC block plugins.
 */
interface HPCPluginInterface {

  /**
   * Get the plugin label.
   */
  public function label();

  /**
   * Get the URI of the current page.
   *
   * Can also be a different page then the current one (e.g. in download
   * contexts or in IPE contexts). The URI should be set as early as possible
   * by any governing logic so that other parts of the code can rely on it.
   */
  public function getCurrentUri();

  /**
   * Get the plugin id.
   */
  public function getPluginId();

  /**
   * Get the UUID for this block if available.
   */
  public function getUuid();

}
