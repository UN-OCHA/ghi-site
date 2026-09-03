<?php

namespace Drupal\Tests\ghi_blocks\Unit\Form;

use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ghi_blocks\Form\InlineOnlyFormErrorHandler;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the inline-only form error handler.
 *
 * @group ghi_blocks
 */
class InlineOnlyFormErrorHandlerTest extends UnitTestCase {

  /**
   * Tests unmarked forms keep the decorated summary errors.
   */
  public function testUnmarkedFormsKeepDecoratedSummaryErrors(): void {
    $messenger = $this->mockMessenger();
    $handler = new InlineOnlyFormErrorHandler($this->mockDecoratedHandler($messenger), $messenger);
    $form = [
      'field' => [],
    ];
    $form_state = $this->createFormStateWithError();

    $handler->handleFormErrors($form, $form_state);

    $this->assertSame('Field is required.', $form['field']['#errors']);
    $this->assertSame(['Form summary error.'], $messenger->messagesByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * Tests marked forms keep inline errors but clear summary messages.
   */
  public function testMarkedFormsClearSummaryErrors(): void {
    $messenger = $this->mockMessenger();
    $messenger->addError('Stale modal error.');
    $handler = new InlineOnlyFormErrorHandler($this->mockDecoratedHandler($messenger), $messenger);
    $form = [
      '#ghi_inline_errors_only' => TRUE,
      'field' => [],
    ];
    $form_state = $this->createFormStateWithError();

    $handler->handleFormErrors($form, $form_state);

    $this->assertSame('Field is required.', $form['field']['#errors']);
    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new FormState())->clearErrors();
    parent::tearDown();
  }

  /**
   * Get a form state with a field error.
   *
   * @return \Drupal\Core\Form\FormState
   *   A form state with a single error.
   */
  private function createFormStateWithError(): FormState {
    $form_state = new FormState();
    $form_state->setErrorByName('field', 'Field is required.');
    return $form_state;
  }

  /**
   * Get a decorated form error handler.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   *
   * @return \Drupal\Core\Form\FormErrorHandlerInterface
   *   A form error handler test double.
   */
  private function mockDecoratedHandler(MessengerInterface $messenger): FormErrorHandlerInterface {
    return new class($messenger) implements FormErrorHandlerInterface {

      /**
       * The messenger service.
       *
       * @var \Drupal\Core\Messenger\MessengerInterface
       */
      private MessengerInterface $messenger;

      /**
       * Constructs a decorated form error handler test double.
       *
       * @param \Drupal\Core\Messenger\MessengerInterface $messenger
       *   The messenger service.
       */
      public function __construct(MessengerInterface $messenger) {
        $this->messenger = $messenger;
      }

      /**
       * {@inheritdoc}
       */
      public function handleFormErrors(array &$form, FormStateInterface $form_state) {
        $errors = $form_state->getErrors();
        $form['field']['#errors'] = reset($errors);
        $this->messenger->addError('Form summary error.');
        return $this;
      }

    };
  }

  /**
   * Get a messenger service.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   A messenger test double.
   */
  private function mockMessenger(): MessengerInterface {
    return new class() implements MessengerInterface {

      /**
       * Messages keyed by type.
       *
       * @var array
       */
      private array $messages = [];

      /**
       * {@inheritdoc}
       */
      public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE) {
        if ($repeat || !in_array($message, $this->messages[$type] ?? [], TRUE)) {
          $this->messages[$type][] = $message;
        }
        return $this;
      }

      /**
       * {@inheritdoc}
       */
      public function addStatus($message, $repeat = FALSE) {
        return $this->addMessage($message, self::TYPE_STATUS, $repeat);
      }

      /**
       * {@inheritdoc}
       */
      public function addError($message, $repeat = FALSE) {
        return $this->addMessage($message, self::TYPE_ERROR, $repeat);
      }

      /**
       * {@inheritdoc}
       */
      public function addWarning($message, $repeat = FALSE) {
        return $this->addMessage($message, self::TYPE_WARNING, $repeat);
      }

      /**
       * {@inheritdoc}
       */
      public function all() {
        return $this->messages;
      }

      /**
       * {@inheritdoc}
       */
      public function messagesByType($type) {
        return $this->messages[$type] ?? [];
      }

      /**
       * {@inheritdoc}
       */
      public function deleteAll() {
        $messages = $this->messages;
        $this->messages = [];
        return $messages;
      }

      /**
       * {@inheritdoc}
       */
      public function deleteByType($type) {
        $messages = $this->messages[$type] ?? [];
        unset($this->messages[$type]);
        return $messages;
      }

    };
  }

}
