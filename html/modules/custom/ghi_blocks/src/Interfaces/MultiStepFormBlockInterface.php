<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks having automatic titles.
 */
interface MultiStepFormBlockInterface {

  /**
   * Define subforms for the block configuration.
   *
   * This allows implementing block plugins to define more complex config
   * forms, using AJAX based multi-step forms. The main logic is handled in
   * this base class. All that implementing classes need to do is to return an
   * associative array, where the keys are the "machine name" of the form,
   * used to store values in the block configuration array, and the value is
   * the name of a callable method on the class that provides the form array
   * for each step.
   */
  public function getSubforms();

}
