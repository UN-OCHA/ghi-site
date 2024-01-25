<?php

namespace Drupal\ghi_form_elements;

/**
 * Interface for configuration container item group plugins.
 */
interface ConfigurationContainerItemGroupInterface extends ConfigurationContainerItemPluginInterface {

  /**
   * Get the configured link if any.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  public function getLink();

}
