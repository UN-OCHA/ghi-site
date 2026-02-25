<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * @covers Drupal\hpc_common\Helpers\StringHelper
 */
class StringHelperTest extends UnitTestCase {

  /**
   * Data provider for makeCamelCase.
   */
  public function makeCamelCaseDataProvider() {
    return [
      ['camel_test', FALSE, 'CamelTest'],
      ['Hardik_Pandya', TRUE, 'hardikPandya'],
    ];
  }

  /**
   * Test to make a string camelcase.
   *
   * @group StringHelper
   * @dataProvider makeCamelCaseDataProvider
   */
  public function testMakeCamelCase($string, $initial_lower_case, $result) {
    $this->assertEquals($result, StringHelper::makeCamelCase($string, $initial_lower_case));
  }

  /**
   * Data provider for testCamelCaseToUnderscoreCase.
   */
  public function camelCaseToUnderscoreCaseDataProvider() {
    return [
      ['camelCase', 'camel_case'],
      ['camelCaseCase', 'camel_case_case'],
      ['CamelCaseCase', 'camel_case_case'],
      ['CCC', 'ccc'],
      ['CaCaCa', 'ca_ca_ca'],
    ];
  }

  /**
   * Test making string camel case.
   *
   * @group StringHelper
   * @dataProvider camelCaseToUnderscoreCaseDataProvider
   */
  public function testCamelCaseToUnderscoreCase($string, $result) {
    $this->assertEquals($result, StringHelper::camelCaseToUnderscoreCase($string));
  }

  /**
   * Data provider for testGetAbbreviation.
   */
  public function getAbbreviationDataProvider() {
    return [
      ['camelCase', 'camelCase'],
      ['camel Case Case', 'CCC'],
      ['Camel Case Case', 'CCC'],
      ['Camel  Case  Case', 'CCC'],
      ['Content Security Policy', 'CSP'],
    ];
  }

  /**
   * Test making string camel case.
   *
   * @group StringHelper
   * @dataProvider getAbbreviationDataProvider
   */
  public function testGetAbbreviation($string, $result) {
    $this->assertEquals($result, StringHelper::getAbbreviation($string));
  }

  /**
   * Data provider for renderString.
   */
  public function renderStringDataProvider() {
    return [
      ['<h1>Hello World!</h1>', FALSE, '<h1>Hello World!</h1>'],
      ['<h1>Hello World!</h1>', TRUE, 'Hello World!'],
    ];
  }

  /**
   * Test rendering a string.
   *
   * @group StringHelper
   * @dataProvider renderStringDataProvider
   */
  public function testRenderString($string, $is_export, $result) {
    $this->assertEquals($result, StringHelper::renderString($string, $is_export));
  }

}
