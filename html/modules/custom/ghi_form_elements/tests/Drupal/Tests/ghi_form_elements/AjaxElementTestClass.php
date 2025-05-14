<?php

namespace Drupal\Tests\ghi_form_elements;

use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Test class only to use the trait conveniently.
 */
class AjaxElementTestClass {

  use AjaxElementTrait;

  /**
   * Return a string for class building.
   *
   * @return string
   *   A string.
   */
  public static function getFormId() {
    return 'form_id';
  }

  /**
   * Grant access to the protected variable.
   *
   * @return string[]
   *   An array of element parents
   */
  public function getElementParentsFormKey() {
    return self::$elementParentsFormKey;
  }

}
