<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Prophecy\PhpUnit\ProphecyTrait;
use Twig\Environment;

/**
 * @covers Drupal\hpc_common\Helpers\ThemeHelper
 */
class ThemeHelperTest extends UnitTestCase {

  use ProphecyTrait;

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
  public function themeDataProvider() {
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
   *
   * @group ThemeHelper
   * @dataProvider themeDataProvider
   */
  public function testTheme($theme_key, $options, $cast_to_string, $xss_filter, $result) {
    $this->assertEquals($result, ThemeHelper::theme($theme_key, $options, $cast_to_string, $xss_filter));
  }

  /**
   * Data provider for testGetThemeOptions.
   */
  public function getThemeOptionsDataProvider() {
    // @codingStandardsIgnoreStart
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
    // @codingStandardsIgnoreEnd
    return $items;
  }

  /**
   * Test calling the theme function.
   *
   * @group ThemeHelper
   * @dataProvider getThemeOptionsDataProvider
   */
  public function testGetThemeOptions($theme_function, $value, $options, $expected) {
    if ($expected instanceof \Exception) {
      $this->expectExceptionObject($expected);
    }
    $build = ThemeHelper::getThemeOptions($theme_function, $value, $options);
    $this->assertEquals($expected, $build);
  }

  /**
   * Test the getNumberSuffix function.
   *
   * @group ThemeHelper
   */
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
   * Test the themeFtsIcon function.
   *
   * @group ThemeHelper
   */
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
