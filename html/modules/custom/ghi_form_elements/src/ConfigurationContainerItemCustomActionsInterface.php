<?php

namespace Drupal\ghi_form_elements;

/**
 * Interface for configuration container item plugins with custom actions.
 */
interface ConfigurationContainerItemCustomActionsInterface {

  /**
   * Get the custom actions for a configuration container item.
   *
   * @return array
   *   An array of actions, the key is used as the form element key, the value
   *   is the action label.
   */
  public function getCustomActions();

}
