<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Form\FormStateInterface;

/**
 * Trait with common helpers to get inliny with GIN LB styles..
 */
trait GinLbModalTrait {

  /**
   * Make the given form a Gin LB form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function makeGinLbForm(array &$form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'canvas-form';
    $form['#attributes']['class'][] = 'ghi-glb-form';
    $form['description']['#type'] = 'container';
    $form['description']['#attributes']['class'][] = 'canvas-form__settings';
    $form['settings']['#type'] = 'container';
    $form['settings']['#attributes']['class'][] = 'canvas-form__settings';
    $form['actions']['#type'] = 'container';
    $form['actions']['#attributes']['class'][] = 'canvas-form__actions';
    $form['actions']['#gin_lb_form'] = TRUE;
    $form['#gin_lb_form'] = TRUE;
    $form['#ghi_modal_form'] = TRUE;
    $form['#after_build'][] = 'gin_lb_after_build';
  }

}
