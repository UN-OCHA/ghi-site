<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Ajax switcher form.
 */
class AjaxSwitcherForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    static $count = 0;
    $count++;
    return 'ajax_switcher_form_' . $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $element_key = NULL, $plugin_id = NULL, $block_uuid = NULL, $options = NULL, $default_value = NULL, $uri = NULL, $query = []) {
    $url = !empty($plugin_id) && !empty($block_uuid) ? Url::fromRoute('ghi_blocks.load_block', [
      'plugin_id' => $plugin_id,
      'block_uuid' => $block_uuid,
    ]) : NULL;

    // Sanity check.
    if (!is_array(reset($options))) {
      // Check a flat list of options.
      if (!array_key_exists($default_value, $options)) {
        $default_value = NULL;
      }
    }
    else {
      // Or check a grouped list of options.
      $found = FALSE;
      foreach ($options as $group) {
        if (array_key_exists($default_value, $group)) {
          $found = TRUE;
          continue;
        }
      }
      if (!$found) {
        $default_value = NULL;
      }
    }

    $form['#gin_lb_form'] = FALSE;
    $form[$element_key] = [
      '#type' => 'select',
      '#title' => NULL,
      '#gin_lb_form_element' => FALSE,
      '#options' => $options,
      '#default_value' => $default_value,
      '#ajax' => $url ? [
        'wrapper' => Html::getId('block-' . $block_uuid),
        'event' => 'change',
        'progress' => 'throbber',
        'url' => $url,
        'options' => [
          'query' => [
            'current_uri' => $uri,
          ] + $query,
        ],
      ] : NULL,
    ];
    $form_state->setMethod('GET');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
