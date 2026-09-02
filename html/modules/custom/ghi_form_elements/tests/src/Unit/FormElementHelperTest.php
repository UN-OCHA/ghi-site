<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;

/**
 * @covers Drupal\ghi_form_elements\Helpers\FormElementHelper
 */
class FormElementHelperTest extends UnitTestCase {

  /**
   * Data provider for testGetStateSelectorFromParents.
   */
  public function getStateSelectorFromParentsDataProvider() {
    return [
      'single parent with subkey' => [
        ['foo'],
        ['bar'],
        'foo[bar]',
      ],
      'multiple parents with subkey' => [
        ['a', 'b'],
        ['c'],
        'a[b][c]',
      ],
      'multiple parents with multiple subkeys' => [
        ['a', 'b'],
        ['c', 'd'],
        'a[b][c][d]',
      ],
      'no subkeys' => [
        ['a', 'b'],
        [],
        'a[b]',
      ],
      'single parent no subkeys' => [
        ['foo'],
        [],
        'foo[]',
      ],
    ];
  }

  /**
   * Test getStateSelectorFromParents method.
   *
   * @dataProvider getStateSelectorFromParentsDataProvider
   * @group FormElementHelper
   */
  public function testGetStateSelectorFromParents(array $parents, array $subkeys, string $expected) {
    $result = FormElementHelper::getStateSelectorFromParents($parents, $subkeys);
    $this->assertSame($expected, $result);
  }

  /**
   * Test getStateSelector method.
   *
   * @group FormElementHelper
   */
  public function testGetStateSelector() {
    $element = [
      '#parents' => ['form', 'field_name'],
    ];
    $result = FormElementHelper::getStateSelector($element, ['settings']);
    $this->assertSame('form[field_name][settings]', $result);
  }

  /**
   * Test getStateSelector with empty subkeys.
   *
   * @group FormElementHelper
   */
  public function testGetStateSelectorEmptySubkeys() {
    $element = [
      '#parents' => ['form', 'field_name'],
    ];
    $result = FormElementHelper::getStateSelector($element, []);
    $this->assertSame('form[field_name]', $result);
  }

}
