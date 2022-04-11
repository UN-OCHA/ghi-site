<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\Core\Form\FormStateInterface;

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
   * an array describing how it should be used.
   *
   * @return array
   *   An array with the form keys as key and the vaue being an array
   *   containing these keys:
   *   - title: The title to be displayed on the button that activates a form.
   *   - callback: A callable method on the implementing plugin class.
   */
  public function getSubforms();

  /**
   * Return the machine name of the form to be used as default.
   *
   * @param bool $is_new
   *   Flag indicating whether this is a new block or an existing one.
   *
   * @return string
   *   The default form key.
   */
  public function getDefaultSubform($is_new = FALSE);

  /**
   * Decide if the given subform can show.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $subform_key
   *   The subform key.
   *
   * @return bool
   *   Whether the form can display the given subform.
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key);

}
