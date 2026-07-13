<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests preprocess functions for HPC Common theme hooks.
 *
 * @group hpc_common
 */
class HpcCommonThemePreprocessTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();

    require_once __DIR__ . '/../../../hpc_common.theme.inc';
  }

  /**
   * Data provider for testPreprocessHpcPercent.
   */
  public function preprocessHpcPercentDataProvider() {
    return [
      [15.96, NULL, FALSE, '16.0<span class="suffix">%</span>'],
      [15.96, NULL, TRUE, '16<span class="suffix">%</span>'],
      [16.14, NULL, TRUE, '16.1<span class="suffix">%</span>'],
      [16.04, 1, TRUE, '16.0<span class="suffix">%</span>'],
    ];
  }

  /**
   * Tests percentage formatting.
   *
   * @dataProvider preprocessHpcPercentDataProvider
   */
  public function testPreprocessHpcPercent(float $percent, ?int $precision, bool $compact_precision, string $expected) {
    $variables = [
      'percent' => $percent,
      'ratio' => NULL,
      'export' => FALSE,
      'precision' => $precision,
      'decimal_format' => NULL,
      'compact_precision' => $compact_precision,
    ];

    \hpc_common_preprocess_hpc_percent($variables);

    $this->assertSame($expected, (string) $variables['output']);
    $this->assertSame(['data-value' => $percent], $variables['attributes']);
  }

}
