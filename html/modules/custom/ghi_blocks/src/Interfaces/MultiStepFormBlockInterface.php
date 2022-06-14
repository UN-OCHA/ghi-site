<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for blocks having automatic titles.
 *
 * Define subforms for the block configuration via the annotation in the block
 * plugin class file.
 *
 * This allows block plugins to define more complex config forms, using AJAX
 * based multi-step forms. The main logic must be handled in the base class.
 * All that implementing classes need to do is to define their config forms via
 * the block annotation.
 *
 * @Block(
 *  ..
 *  config_forms = {
 *    "common_settings" = {
 *      "title" = @Translation("Common settings"),
 *      "callback" = "commonSettingsForm",
 *      "base_form" = TRUE
 *    },
 *    "specific_settings" = {
 *      "title" = @Translation("Specific settings"),
 *      "callback" = "specificSettingsForm"
 *    }
 *  }
 * )
 *
 * The keys are the "machine name" of the form for internal use and to store
 * configuration values in the block configuration array. The value is an
 * object describing how it should be used.
 */
interface MultiStepFormBlockInterface {

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
