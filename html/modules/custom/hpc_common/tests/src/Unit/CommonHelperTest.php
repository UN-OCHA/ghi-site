<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\CommonHelper;
use Twig\Environment;

/**
 * @covers Drupal\hpc_common\Helpers\CommonHelper
 */
class CommonHelperTest extends UnitTestCase {

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
    $this->assertEquals($result, CommonHelper::calculateRatio($value1, $value2, $round));
  }

  /**
   * Data provider for renderValue.
   */
  public function renderValueDataProvider() {
    return [
      ['100000', 'amount', 'hpc_amount', [], NULL, NULL, FALSE, 100000],
      ['100000', 'amount', 'hpc_amount', ['scale' => 'full'], NULL, NULL, FALSE, 100000],
      ['', 'amount', 'hpc_amount', [], NULL, NULL, FALSE, '<span class="empty pending">Pending</span>'],
      ['', 'amount', 'hpc_amount', [], 'Pending', NULL, FALSE, '<span class="empty pending">Pending</span>'],
      [NULL, 'amount', 'hpc_amount', [], NULL, 'No data', FALSE, '<span class="empty not-available">No data</span>'],
      [NULL, 'amount', 'hpc_amount', [], NULL, NULL, TRUE, '<span class="empty not-available"></span>'],
    ];
  }

  /**
   * Test calculating ratio.
   *
   * @group CommonHelper
   * @dataProvider renderValueDataProvider
   */
  public function testRenderValue($value, $theme_key, $theme_function, $theme_args, $pending_string, $not_available_string, $is_export, $result) {

    // Mock renderer service.
    $renderer = $this->prophesize(RendererInterface::class);
    $twig = $this->prophesize(Environment::class);
    $path_resolver = $this->prophesize(ExtensionPathResolver::class);
    $path_resolver->getPath('module', 'hpc_common')->willReturn('path');

    // Mock render.
    $build = [
      '#theme' => $theme_function,
      '#' . $theme_key => $value,
    ];
    foreach ($theme_args as $arg => $val) {
      $build['#' . $arg] = $val;
    }
    $renderer->hasRenderContext()->willReturn(TRUE);
    $renderer->render($build)->willReturn($value);

    $twig->isDebug()->willReturn(FALSE);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer->reveal());
    $container->set('twig', $twig->reveal());
    $container->set('extension.path.resolver', $path_resolver->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->assertEquals($result, CommonHelper::renderValue($value, $theme_key, $theme_function, $theme_args, $pending_string, $not_available_string, $is_export));
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
    $this->assertEquals($result, CommonHelper::canBeCastToString($item));
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
    $this->assertEquals($result, CommonHelper::removeDiacritics($string));
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
    $this->assertEquals($result, CommonHelper::sanitizeDisplayKey($string));
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
    $this->assertEquals($result, CommonHelper::sanitizeLabel($label));
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
      ['/appeals/645/summary',
        [
          2 => NULL,
        ],
        [
          'path' => 'appeals/645',
          'query' => [],
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
    $this->assertEquals($result, CommonHelper::replaceInUrl($url, $replacements));
  }

  /**
   * Data provider for assureWellFormedUri.
   */
  public function assureWellFormedUriDataProvider() {
    return [
      [
        '', NULL,
      ],
      [
        'google.com', 'http://google.com',
      ],
      [
        'https://', NULL,
      ],
    ];
  }

  /**
   * Test replacing placehlders in the URL.
   *
   * @group CommonHelper
   * @dataProvider assureWellFormedUriDataProvider
   */
  public function testAssureWellFormedUri($url, $expected) {
    $this->assertEquals($expected, CommonHelper::assureWellFormedUri($url));
  }

}
