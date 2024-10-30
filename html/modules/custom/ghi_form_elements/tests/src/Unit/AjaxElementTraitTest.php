<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ghi_form_elements\AjaxElementTestClass;

/**
 * @covers Drupal\ghi_form_elements\Traits\AjaxElementTrait
 */
class AjaxElementTraitTest extends UnitTestCase {

  /**
   * Test getWrapperId.
   */
  public function testGetWrapperId() {
    $class = new AjaxElementTestClass();

    $element = [
      '#array_parents' => ['one', 'two'],
    ];
    $this->assertEquals('one-two-wrapper', $class->getWrapperId($element));
    $element = [
      '#array_parents' => ['one', 'two'],
      '#attributes' => ['class' => ['class_1', 'class_2']],
    ];
    $this->assertEquals('one-two-wrapper', $class->getWrapperId($element));
    $element = [
      '#attributes' => ['class' => ['class_1', 'class_2']],
    ];
    $this->assertEquals('class-1-wrapper', $class->getWrapperId($element));
    $element = [
      '#attributes' => ['class' => ['class_1', 'class_2']],
    ];
    $this->assertEquals('form-id-wrapper', $class->getWrapperId([]));
  }

  /**
   * Test setElementParents.
   */
  public function testSetElementParents() {
    $class = new AjaxElementTestClass();
    // Make the protected method callable.
    $method = (new \ReflectionClass(AjaxElementTestClass::class))->getMethod('setElementParents');

    $array_parents = ['one', 'two'];
    $method->invokeArgs($class, [['#array_parents' => $array_parents]]);
    $this->assertEquals($array_parents, $class->getElementParentsFormKey());

    $method->invokeArgs($class, [[]]);
    $this->assertEquals([], $class->getElementParentsFormKey());
  }

  /**
   * Test updateAjax.
   */
  public function testUpdateAjax() {
    $class = new AjaxElementTestClass();

    // Make the protected method callable.
    $method = (new \ReflectionClass(AjaxElementTestClass::class))->getMethod('setElementParents');

    $array_parents = ['one', 'two'];
    $method->invokeArgs($class, [['#array_parents' => $array_parents]]);

    $form = [];
    $triggering_elements = [
      '#ajax' => [
        'wrapper' => 'wrapper-id',
      ],
    ];
    // Need to use mocks for the form states, because getTriggeringElement()
    // returns as a reference and prophesized doubles don't support that.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->any())
      ->method('getTriggeringElement')
      ->willReturnReference($triggering_elements);

    $response = $class->updateAjax($form, $form_state);
    $this->assertInstanceOf(AjaxResponse::class, $response);
  }

  /**
   * Test setClassOnAjaxElements.
   */
  public function testSetClassOnAjaxElements() {
    $class = new AjaxElementTestClass();
    // Make the protected method callable.
    $method = (new \ReflectionClass(AjaxElementTestClass::class))->getMethod('setClassOnAjaxElements');

    $element = [];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals([], $element);

    $element = [
      '#ajax' => [],
      '#attributes' => ['class' => []],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['ajax-enabled'], $element['#attributes']['class']);

    $element = [
      '#ajax' => [],
      '#attributes' => ['class' => ['test-class']],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['test-class', 'ajax-enabled'], $element['#attributes']['class']);

    $element = [
      '#type' => 'container',
      'child' => [
        '#ajax' => [],
        '#attributes' => ['class' => []],
      ],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['ajax-enabled'], $element['child']['#attributes']['class']);
  }

  /**
   * Test hideAllElements.
   */
  public function testHideAllElements() {
    $class = new AjaxElementTestClass();
    // Make the protected method callable.
    $method = (new \ReflectionClass(AjaxElementTestClass::class))->getMethod('hideAllElements');

    $element = [];
    $expected_element = [
      '#title_display' => 'invisible',
      '#attributes' => ['class' => ['visually-hidden']],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals($expected_element, $element);

    $element = [
      '#attributes' => ['class' => []],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['visually-hidden'], $element['#attributes']['class']);

    $element = [
      '#attributes' => ['class' => ['test-class']],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['test-class', 'visually-hidden'], $element['#attributes']['class']);

    $element = [
      '#type' => 'container',
      'child' => [
        '#attributes' => ['class' => []],
      ],
    ];
    $method->invokeArgs($class, [&$element]);
    $this->assertEquals(['visually-hidden'], $element['child']['#attributes']['class']);
  }

  /**
   * Test getActionFromFormState.
   */
  public function testGetActionFromFormState() {
    $class = new AjaxElementTestClass();
    $element = [
      '#array_parents' => ['one', 'two'],
    ];
    // Need to use mocks for the form states, because getTriggeringElement()
    // returns as a reference and prophesized doubles don't support that.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->any())
      ->method('getTriggeringElement')
      ->willReturnReference($element);
    $action = $class->getActionFromFormState($form_state);
    $this->assertEquals('two', $action);

    $element = NULL;
    $form_state->expects($this->any())
      ->method('getTriggeringElement')
      ->willReturnReference($element);
    $this->assertNull($class->getActionFromFormState($form_state));
  }

}
