<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for blocks having automatic titles.
 */
interface SimpleFormBlockInterface {

  /**
   * Get a title for a block instance.
   *
   * @return string|null
   *   The title string or NULL if none is available.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state);

}
