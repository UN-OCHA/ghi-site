<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @covers Drupal\hpc_common\Helpers\CommonHelper
 */
class CommonHelperTest extends UnitTestCase {

  /**
   * The common helper class.
   *
   * @var \Drupal\hpc_common\Helpers\CommonHelper
   */
  protected $commonHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->commonHelper = new CommonHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->commonHelper);
  }

  /**
   * Data provider for calculateRatio.
   */
  public function calculateRatioDataProvider() {
    return [
      ['5', '10', '1', '0.5'],
      ['7', '20', '2', '0.35'],
      ['8', '21', '3', '0.381'],
    ];
  }

  /**
   * Test calculating ratio.
   *
   * @group CommonHelper
   * @dataProvider calculateRatioDataProvider
   */
  public function testCalculateRatio($value1, $value2, $round, $result) {
    $this->assertEquals($result, $this->commonHelper->calculateRatio($value1, $value2, $round));
  }

  /**
   * Data provider for canBeCastToString.
   */
  public function canBeCastToStringDataProvider() {
    $object = new \stdClass();
    $object->name = 'Jon Snow';
    return [
      [['Hello world'], FALSE],
      ['Hello world', TRUE],
      [$object, FALSE],
    ];
  }

  /**
   * Test casting to string.
   *
   * @group CommonHelper
   * @dataProvider canBeCastToStringDataProvider
   */
  public function testCanBeCastToString($item, $result) {
    $this->assertEquals($result, $this->commonHelper->canBeCastToString($item));
  }

  /**
   * Data provider for removeDiacritics.
   */
  public function removeDiacriticsDataProvider() {
    return [
      ['tÈtέ', 'tEtε'],
      ['hôpital', 'hopital'],
      ['français', 'francais'],
    ];
  }

  /**
   * Test removing diacritics.
   *
   * @group CommonHelper
   * @dataProvider removeDiacriticsDataProvider
   */
  public function testRemoveDiacritics($string, $result) {
    $this->assertEquals($result, $this->commonHelper->removeDiacritics($string));
  }

  /**
   * Data provider for sanitizeDisplayKey.
   */
  public function sanitizeDisplayKeyDataProvider() {
    return [
      ['ds=a_565ÆÇ', 'dsa565AEC'],
      ['gy__YJi+-*/12', 'gyYJi12'],
    ];
  }

  /**
   * Test sanitizing a display key.
   *
   * @group CommonHelper
   * @dataProvider sanitizeDisplayKeyDataProvider
   */
  public function testSanitizeDisplayKey($string, $result) {
    $this->assertEquals($result, $this->commonHelper->sanitizeDisplayKey($string));
  }

  /**
   * Data provider for sanitizeLabel.
   */
  public function sanitizeLabelDataProvider() {
    return [
      ['Hello world', 'Hello world'],
      ['<h1>Hello world</h1>', '&lt;h1&gt;Hello world&lt;/h1&gt;'],
    ];
  }

  /**
   * Test sanitizing a label.
   *
   * @group CommonHelper
   * @dataProvider sanitizeLabelDataProvider
   */
  public function testSanitizeLabel($label, $result) {
    $this->assertEquals($result, $this->commonHelper->sanitizeLabel($label));
  }

  /**
   * Data provider for replaceInUrl.
   */
  public function replaceInUrlDataProvider() {
    return [
      ['/appeals/645/summary?name=hardik&sort=ASC',
        [
          2 => 'flows',
        ],
        [
          'path' => 'appeals/645/flows',
          'query' => [
            'name' => 'hardik',
          ],
        ],
      ],
      ['/countries/1/donors/2018?page=/appeals/645/flows&order=id',
        [
          1 => '210',
          3 => '2019',
        ],
        [
          'path' => 'countries/210/donors/2019',
          'query' => [],
        ],
      ],
    ];
  }

  /**
   * Test replacing placehlders in the URL.
   *
   * @group CommonHelper
   * @dataProvider replaceInUrlDataProvider
   */
  public function testReplaceInUrl($url, $replacements, $result) {
    $this->assertEquals($result, $this->commonHelper->replaceInUrl($url, $replacements));
  }

}
