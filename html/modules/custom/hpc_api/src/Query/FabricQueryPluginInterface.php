<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for fabric query plugins.
 */
interface FabricQueryPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Disable caching.
   *
   * @return static
   *   Returns the query instance for chaining.
   */
  public function disableCache(): static;

}
