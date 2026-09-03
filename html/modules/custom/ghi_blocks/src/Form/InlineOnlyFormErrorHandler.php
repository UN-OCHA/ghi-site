<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Keeps validation errors inline for marked modal configuration forms.
 */
class InlineOnlyFormErrorHandler implements FormErrorHandlerInterface {

  /**
   * The decorated form error handler.
   *
   * @var \Drupal\Core\Form\FormErrorHandlerInterface
   */
  private FormErrorHandlerInterface $decorated;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * Constructs an inline-only form error handler.
   *
   * @param \Drupal\Core\Form\FormErrorHandlerInterface $decorated
   *   The decorated form error handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(FormErrorHandlerInterface $decorated, MessengerInterface $messenger) {
    $this->decorated = $decorated;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function handleFormErrors(array &$form, FormStateInterface $form_state) {
    $this->decorated->handleFormErrors($form, $form_state);

    if (!empty($form['#ghi_inline_errors_only']) && $form_state->getErrors()) {
      // AJAX modal responses replace only the edited form fragment, so summary
      // messages queued for inline field errors can leak into later requests.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
    }

    return $this;
  }

}
