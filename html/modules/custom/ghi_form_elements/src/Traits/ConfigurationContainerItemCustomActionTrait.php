<?php

namespace Drupal\ghi_form_elements\Traits;

/**
 * Helper trait for plugins using custom actions.
 */
trait ConfigurationContainerItemCustomActionTrait {

  /**
   * {@inheritdoc}
   */
  public function isValidAction($action) {
    return TRUE;
  }

}
