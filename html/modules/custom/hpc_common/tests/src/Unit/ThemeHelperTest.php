<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * @covers Drupal\hpc_common\Helpers\ThemeHelper
 */
class ThemeHelperTest extends UnitTestCase {

  /**
   * The theme helper class.
   *
   * @var \Drupal\hpc_common\Helpers\ThemeHelper
   */
  protected $themeHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock renderer service.
    $renderer = $this->prophesize(RendererInterface::class);

    // Mock render.
    $build = [
      '#theme' => 'test_theme',
      '#name' => 'Jon Snow',
    ];
    $renderer->hasRenderContext()->willReturn(TRUE);
    $renderer->render($build)->willReturn('<h1>My name is Jon Snow</h1>');

    // Set container.
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer->reveal());
    \Drupal::setContainer($container);

    $this->themeHelper = new ThemeHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->themeHelper);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for theme.
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
    $this->assertEquals($result, $this->themeHelper->theme($theme_key, $options, $cast_to_string, $xss_filter));
  }

}
