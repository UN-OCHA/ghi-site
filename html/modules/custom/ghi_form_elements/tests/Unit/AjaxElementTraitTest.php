<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * @covers Drupal\ghi_form_elements\Traits\AjaxElementTrait
 */
class AjaxElementTraitTest extends UnitTestCase {

  /**
   * Test getWrapperId.
   */
  public function testGetWrapperId() {
    /** @var \Drupal\ghi_form_elements\Traits\AjaxElementTrait $trait */
    $trait = $this->getObjectForTrait(AjaxElementTrait::class);

    $element = [
      '#array_parents' => ['one', 'two'],
    ];
    $this->assertEquals('one-two-wrapper', $trait->getWrapperId($element));
    $element = [
      '#array_parents' => ['one', 'two'],
      '#attributes' => ['class' => ['class_1', 'class_2']],
    ];
    $this->assertEquals('one-two-wrapper', $trait->getWrapperId($element));
    $element = [
      '#attributes' => ['class' => ['class_1', 'class_2']],
    ];
    $this->assertEquals('class-1-wrapper', $trait->getWrapperId($element));
  }

}
