<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\hpc_common\Helpers\StringHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @covers Drupal\hpc_common\Helpers\StringHelper
 */
class StringHelperTest extends UnitTestCase {

  /**
   * The string helper class.
   *
   * @var Drupal\hpc_common\Helpers\StringHelper
   */
  protected $stringHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stringHelper = new StringHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->stringHelper);
  }

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
    $this->assertEquals($result, $this->stringHelper->makeCamelCase($string, $initial_lower_case));
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
    $this->assertEquals($result, $this->stringHelper->renderString($string, $is_export));
  }

}
