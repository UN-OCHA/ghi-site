<?php

namespace Drupal\ghi_form_elements;

/**
 * Interface for configuration container item group plugins.
 */
interface ConfigurationContainerItemGroupInterface extends ConfigurationContainerItemPluginInterface {

  /**
   * Check if the group has an additional link configured.
   *
   * @return bool
   *   TRUE if there is a link to be displayed, FALSE otherwise.
   */
  public function hasLink();

  /**
   * Get the configured link if any.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  public function getLink();

}
