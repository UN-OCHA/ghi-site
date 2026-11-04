<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Cancel button for structural confirmation dialogs.
 */
abstract class SubpagesStructureConfirmFormBase extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if ($this->getRequest()->isXmlHttpRequest()) {
      // Keep core's cancel link on direct visits. In AJAX dialogs, a submit
      // control joins Confirm in the button pane instead of the dialog body.
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->getCancelText(),
        '#limit_validation_errors' => [],
        '#submit' => ['::cancelForm'],
        '#ajax' => ['callback' => '::ajaxCancel'],
      ];
    }

    return $form;
  }

  /**
   * Returns to the subpages listing without changing the page structure.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    // Drupal's destination would override the fallback redirect and could
    // send a cancelled action to content admin instead of this listing.
    $form_state->setIgnoreDestination();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Closes the dialog after Cancel is submitted through AJAX.
   */
  public function ajaxCancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());
    return $response;
  }

}
