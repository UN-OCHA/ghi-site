<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ThemeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests preprocess functions for HPC Common theme hooks.
 */
#[Group('hpc_common')]
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
  public static function preprocessHpcPercentDataProvider() {
    return [
      [15.96, NULL, FALSE, '16.0<span class="suffix">%</span>'],
      [15.96, NULL, TRUE, '16<span class="suffix">%</span>'],
      [16.14, NULL, TRUE, '16.1<span class="suffix">%</span>'],
      [16.04, 1, TRUE, '16.0<span class="suffix">%</span>'],
      [29.349999999999998, NULL, TRUE, '29.4<span class="suffix">%</span>'],
      [29.344999999999999, NULL, TRUE, '29.3<span class="suffix">%</span>'],
    ];
  }

  /**
   * Tests percentage formatting.
   */
  #[DataProvider('preprocessHpcPercentDataProvider')]
  public function testPreprocessHpcPercent(float $percent, ?int $precision, bool $compact_precision, string $expected) {
    $this->setNumericDisplayStyle(ThemeHelper::DISPLAY_STYLE_STANDARD);
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

  /**
   * Tests the compact percentage display style.
   */
  public function testPreprocessHpcPercentCompact(): void {
    $variables = [
      'percent' => 31.5,
      'ratio' => NULL,
      'export' => FALSE,
      'precision' => NULL,
      'decimal_format' => NULL,
      'compact_precision' => FALSE,
      'table_cell' => TRUE,
    ];

    \hpc_common_preprocess_hpc_percent($variables);

    $this->assertSame('32<span class="suffix">%</span>', (string) $variables['output']);
    $this->assertSame(['data-value' => 31.5], $variables['attributes']);
  }

  /**
   * Tests that exports retain Standard percentage precision.
   */
  public function testPreprocessHpcPercentExport(): void {
    $variables = [
      'percent' => 31.5,
      'ratio' => NULL,
      'export' => TRUE,
      'precision' => NULL,
      'decimal_format' => NULL,
      'compact_precision' => FALSE,
      'table_cell' => TRUE,
    ];

    \hpc_common_preprocess_hpc_percent($variables);

    $this->assertSame('31.5%', (string) $variables['output']);
  }

  /**
   * Data provider for compact amount formatting.
   */
  public static function preprocessHpcCompactAmountDataProvider(): array {
    return [
      [15600000, '15.6<span class="suffix">M</span>'],
      [2920000000, '2.9<span class="suffix">B</span>'],
      [269000, '269<span class="suffix">k</span>'],
      [999499, '999<span class="suffix">k</span>'],
      [999500, '1.0<span class="suffix">M</span>'],
      [999949999, '999.9<span class="suffix">M</span>'],
      [999950000, '1.0<span class="suffix">B</span>'],
      [999, '999'],
      [-15600000, '-15.6<span class="suffix">M</span>'],
      [-999500, '-1.0<span class="suffix">M</span>'],
    ];
  }

  /**
   * Tests the compact amount display style.
   */
  #[DataProvider('preprocessHpcCompactAmountDataProvider')]
  public function testPreprocessHpcCompactAmount(float $amount, string $expected): void {
    $variables = [
      'amount' => $amount,
      'scale' => 'auto',
      'suffix' => '',
      'use_abbreviation' => FALSE,
      'include_suffix' => TRUE,
      'export' => FALSE,
      'decimals' => 0,
      'decimal_format' => NULL,
      'table_cell' => TRUE,
    ];

    \hpc_common_preprocess_hpc_amount($variables);

    $this->assertSame($expected, (string) $variables['output']);
    $this->assertSame(['data-value' => $amount], $variables['attributes']);
  }

  /**
   * Tests that non-table output keeps the Standard amount display style.
   */
  public function testPreprocessHpcAmountOutsideTableUsesStandardStyle(): void {
    $variables = [
      'amount' => 15600000,
      'scale' => 'auto',
      'suffix' => '',
      'use_abbreviation' => TRUE,
      'include_suffix' => TRUE,
      'export' => FALSE,
      'decimals' => 1,
      'decimal_format' => NULL,
    ];

    \hpc_common_preprocess_hpc_amount($variables);

    $this->assertSame('15.6<span class="suffix">m</span>', (string) $variables['output']);
  }

  /**
   * Tests that the Standard setting is respected for table cells.
   */
  public function testPreprocessHpcAmountTableUsesConfiguredStandardStyle(): void {
    $this->setNumericDisplayStyle(ThemeHelper::DISPLAY_STYLE_STANDARD);
    $variables = [
      'amount' => 15600000,
      'scale' => 'auto',
      'suffix' => '',
      'use_abbreviation' => TRUE,
      'include_suffix' => TRUE,
      'export' => FALSE,
      'decimals' => 1,
      'decimal_format' => NULL,
      'table_cell' => TRUE,
    ];

    \hpc_common_preprocess_hpc_amount($variables);

    $this->assertSame('15.6<span class="suffix">m</span>', (string) $variables['output']);
  }

  /**
   * Set the numeric display style exposed by the mocked configuration service.
   */
  private function setNumericDisplayStyle(string $display_style): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('numeric_display_style')->willReturn($display_style);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('hpc_common.settings')->willReturn($config);
    $container = \Drupal::hasContainer() ? \Drupal::getContainer() : new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    if (!$container->has('string_translation')) {
      $container->set('string_translation', $this->getStringTranslationStub());
    }
    \Drupal::setContainer($container);
  }

}
