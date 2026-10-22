<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ThemeHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;

/**
 * Tests the theme helper.
 */
#[CoversClass(ThemeHelper::class)]
class ThemeHelperTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock renderer service.
    $renderer = $this->prophesize(RendererInterface::class);
    $twig = $this->prophesize(Environment::class);
    $path_resolver = $this->prophesize(ExtensionPathResolver::class);
    $path_resolver->getPath('module', 'hpc_common')->willReturn('path');

    // Mock render.
    $build = [
      '#theme' => 'test_theme',
      '#name' => 'Jon Snow',
    ];
    $renderer->hasRenderContext()->willReturn(TRUE);
    $renderer->render($build)->willReturn('<h1>My name is Jon Snow</h1>');

    $twig->isDebug()->willReturn(FALSE);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer->reveal());
    $container->set('twig', $twig->reveal());
    $container->set('extension.path.resolver', $path_resolver->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for testTheme.
   */
  public static function themeDataProvider() {
    return [
      ['test_theme',
        [
          '#name' => 'Jon Snow',
        ],
        FALSE,
        TRUE,
        [
          '#theme' => 'test_theme',
          '#name' => 'Jon Snow',
        ],
      ],
      ['test_theme',
        [
          '#name' => 'Jon Snow',
        ],
        TRUE,
        FALSE,
        '<h1>My name is Jon Snow</h1>',
      ],
      ['test_theme',
        [
          '#name' => 'Jon Snow',
        ],
        TRUE,
        TRUE,
        'My name is Jon Snow',
      ],
    ];
  }

  /**
   * Test calling the theme function.
   */
  #[Group('ThemeHelper')]
  #[DataProvider('themeDataProvider')]
  public function testTheme($theme_key, $options, $cast_to_string, $xss_filter, $result) {
    $this->assertEquals($result, ThemeHelper::theme($theme_key, $options, $cast_to_string, $xss_filter));
  }

  /**
   * Tests rendering without an existing render context.
   */
  public function testRenderInIsolation() {
    $build = ['#markup' => '<strong>Isolated markup</strong>'];
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('hasRenderContext')->willReturn(FALSE);
    $renderer->expects($this->once())
      ->method('renderInIsolation')
      ->with($build)
      ->willReturn(' <strong>Isolated markup</strong> ');
    $renderer->expects($this->never())->method('render');
    \Drupal::getContainer()->set('renderer', $renderer);

    $this->assertSame('<strong>Isolated markup</strong>', ThemeHelper::render($build, FALSE));
  }

  /**
   * Data provider for testGetThemeOptions.
   */
  public static function getThemeOptionsDataProvider() {
    // phpcs:disable
    $items = [
      // Amount.
      ['hpc_amount', 100, [], [
        '#theme' => 'hpc_amount',
        '#amount' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'point',
        '#decimals' => 0,
      ]],
      ['hpc_amount', 100, ['scale' => 'million'], [
        '#theme' => 'hpc_amount',
        '#amount' => 100,
        '#scale' => 'million',
        '#decimal_format' => 'point',
        '#decimals' => 0,
      ]],
      ['hpc_amount', 100, ['decimal_format' => 'comma'], [
        '#theme' => 'hpc_amount',
        '#amount' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'comma',
        '#decimals' => 0,
      ]],
      ['hpc_amount', 100, ['decimals' => '1'], [
        '#theme' => 'hpc_amount',
        '#amount' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'point',
        '#decimals' => 1,
      ]],
      ['hpc_amount', 100, ['table_cell' => TRUE], [
        '#theme' => 'hpc_amount',
        '#amount' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'point',
        '#decimals' => 0,
        '#table_cell' => TRUE,
      ]],
      // Currency.
      ['hpc_currency', 100, [], [
        '#theme' => 'hpc_currency',
        '#value' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'point',
        '#decimals' => 0,
      ]],
      ['hpc_currency', 100, ['scale' => 'million'], [
        '#theme' => 'hpc_currency',
        '#value' => 100,
        '#scale' => 'million',
        '#decimal_format' => 'point',
        '#decimals' => 0,
      ]],
      ['hpc_currency', 100, ['decimal_format' => 'comma'], [
        '#theme' => 'hpc_currency',
        '#value' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'comma',
        '#decimals' => 0,
      ]],
      ['hpc_currency', 100, ['decimals' => '1'], [
        '#theme' => 'hpc_currency',
        '#value' => 100,
        '#scale' => 'auto',
        '#decimal_format' => 'point',
        '#decimals' => 1,
      ]],
      // Percent.
      ['hpc_percent', 100, [], [
        '#theme' => 'hpc_percent',
        '#percent' => 100,
        '#decimal_format' => 'point',
      ]],
      ['hpc_percent', 100, ['decimal_format' => 'comma'], [
        '#theme' => 'hpc_percent',
        '#percent' => 100,
        '#decimal_format' => 'comma',
      ]],
      ['hpc_percent', 100, ['compact_precision' => TRUE], [
        '#theme' => 'hpc_percent',
        '#percent' => 100,
        '#decimal_format' => 'point',
        '#compact_precision' => TRUE,
      ]],
      // Progress bar.
      ['hpc_progress_bar', 100, [], [
        '#theme' => 'hpc_progress_bar',
        '#percent' => 100,
        '#hide_value' => FALSE,
      ]],
      ['hpc_progress_bar', 100, ['hide_value' => TRUE], [
        '#theme' => 'hpc_progress_bar',
        '#percent' => 100,
        '#hide_value' => TRUE,
      ]],
      // Invalid theme argument.
      ['unknown_theme_function', 100, [], new \InvalidArgumentException('Unknown theme function "unknown_theme_function"')],
    ];
    // phpcs:enable
    return $items;
  }

  /**
   * Test calling the theme function.
   */
  #[Group('ThemeHelper')]
  #[DataProvider('getThemeOptionsDataProvider')]
  public function testGetThemeOptions($theme_function, $value, $options, $expected) {
    if ($expected instanceof \Exception) {
      $this->expectExceptionObject($expected);
    }
    $build = ThemeHelper::getThemeOptions($theme_function, $value, $options);
    $this->assertEquals($expected, $build);
  }

  /**
   * Test the getNumberSuffix function.
   */
  #[Group('ThemeHelper')]
  public function testGetNumberSuffix() {
    $this->assertEquals('k', ThemeHelper::getNumberSuffix('thousand'));
    $this->assertEquals(' thousand', ThemeHelper::getNumberSuffix('thousand', FALSE));
    $this->assertEquals('m', ThemeHelper::getNumberSuffix('million'));
    $this->assertEquals(' million', ThemeHelper::getNumberSuffix('million', FALSE));
    $this->assertEquals('bn', ThemeHelper::getNumberSuffix('billion'));
    $this->assertEquals(' billion', ThemeHelper::getNumberSuffix('billion', FALSE));
    $this->assertEquals('', ThemeHelper::getNumberSuffix('random_string'));
    $this->assertEquals('', ThemeHelper::getNumberSuffix('random_string', FALSE));
  }

  /**
   * Tests compact number suffixes.
   */
  #[Group('ThemeHelper')]
  public function testGetCompactNumberSuffix(): void {
    $this->assertSame('k', ThemeHelper::getCompactNumberSuffix('thousand'));
    $this->assertSame('M', ThemeHelper::getCompactNumberSuffix('million'));
    $this->assertSame('B', ThemeHelper::getCompactNumberSuffix('billion'));
    $this->assertSame('', ThemeHelper::getCompactNumberSuffix('random_string'));
  }

  /**
   * Tests recursively marking numeric render arrays as table-cell output.
   */
  public function testApplyTableNumberFormatting(): void {
    $build = [
      '#type' => 'container',
      'value' => [
        '#theme' => 'hpc_currency',
        '#value' => 1234567,
      ],
      'label' => [
        '#markup' => 'Requirements',
      ],
      '#tooltips' => [
        [
          '#theme' => 'hpc_percent',
          '#percent' => 31.5,
        ],
      ],
    ];

    $formatted = ThemeHelper::applyTableNumberFormatting($build);

    $this->assertTrue($formatted['value']['#table_cell']);
    $this->assertTrue($formatted['#tooltips'][0]['#table_cell']);
    $this->assertArrayNotHasKey('#table_cell', $formatted);
    $this->assertArrayNotHasKey('#table_cell', $formatted['label']);
  }

  /**
   * Tests numeric display style options.
   */
  #[Group('ThemeHelper')]
  public function testGetDisplayStyleOptions(): void {
    $options = ThemeHelper::getDisplayStyleOptions();

    $this->assertSame(ThemeHelper::DISPLAY_STYLE_COMPACT, ThemeHelper::DISPLAY_STYLE_DEFAULT);
    $this->assertSame(['standard', 'compact'], array_keys($options));
    $this->assertSame('Standard', (string) $options['standard']);
    $this->assertSame('Compact', (string) $options['compact']);
  }

  /**
   * Tests numeric display style resolution.
   */
  #[Group('ThemeHelper')]
  public function testIsCompactDisplayStyle(): void {
    $this->assertTrue(ThemeHelper::isCompactDisplayStyle());

    $this->setNumericDisplayStyle(ThemeHelper::DISPLAY_STYLE_STANDARD);
    $this->assertFalse(ThemeHelper::isCompactDisplayStyle());

    $this->setNumericDisplayStyle(ThemeHelper::DISPLAY_STYLE_COMPACT);
    $this->assertTrue(ThemeHelper::isCompactDisplayStyle());

    $this->setNumericDisplayStyle('invalid');
    $this->assertTrue(ThemeHelper::isCompactDisplayStyle());
  }

  /**
   * Set the numeric display style exposed by the mocked configuration service.
   */
  private function setNumericDisplayStyle(string $display_style): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('numeric_display_style')->willReturn($display_style);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('hpc_common.settings')->willReturn($config);
    \Drupal::getContainer()->set('config.factory', $config_factory);
  }

  /**
   * Test the themeFtsIcon function.
   */
  #[Group('ThemeHelper')]
  public function testThemeFtsIcon() {
    $expected = [
      '#theme' => 'image',
      '#uri' => '/path/assets/fts-logo-mobile.png',
      '#attributes' => [
        'class' => 'fts-icon',
      ],
    ];
    $this->assertEquals($expected, ThemeHelper::themeFtsIcon());
  }

}
