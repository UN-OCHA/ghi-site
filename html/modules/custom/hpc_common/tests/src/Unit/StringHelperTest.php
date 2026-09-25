<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Helpers\StringHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the string helper.
 */
#[CoversClass(StringHelper::class)]
class StringHelperTest extends UnitTestCase {

  /**
   * Data provider for makeCamelCase.
   */
  public static function makeCamelCaseDataProvider() {
    return [
      ['camel_test', FALSE, 'CamelTest'],
      ['Hardik_Pandya', TRUE, 'hardikPandya'],
    ];
  }

  /**
   * Test to make a string camelcase.
   */
  #[Group('StringHelper')]
  #[DataProvider('makeCamelCaseDataProvider')]
  public function testMakeCamelCase($string, $initial_lower_case, $result) {
    $this->assertEquals($result, StringHelper::makeCamelCase($string, $initial_lower_case));
  }

  /**
   * Data provider for testCamelCaseToUnderscoreCase.
   */
  public static function camelCaseToUnderscoreCaseDataProvider() {
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
   */
  #[Group('StringHelper')]
  #[DataProvider('camelCaseToUnderscoreCaseDataProvider')]
  public function testCamelCaseToUnderscoreCase($string, $result) {
    $this->assertEquals($result, StringHelper::camelCaseToUnderscoreCase($string));
  }

  /**
   * Data provider for testGetAbbreviation.
   */
  public static function getAbbreviationDataProvider() {
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
   */
  #[Group('StringHelper')]
  #[DataProvider('getAbbreviationDataProvider')]
  public function testGetAbbreviation($string, $result) {
    $this->assertEquals($result, StringHelper::getAbbreviation($string));
  }

  /**
   * Data provider for renderString.
   */
  public static function renderStringDataProvider() {
    return [
      ['<h1>Hello World!</h1>', FALSE, '<h1>Hello World!</h1>'],
      ['<h1>Hello World!</h1>', TRUE, 'Hello World!'],
    ];
  }

  /**
   * Test rendering a string.
   */
  #[Group('StringHelper')]
  #[DataProvider('renderStringDataProvider')]
  public function testRenderString($string, $is_export, $result) {
    $this->assertEquals($result, StringHelper::renderString($string, $is_export));
  }

}
