<?php

namespace Drupal\Tests\hpc_downloads\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\Tests\UnitTestCase;

/**
 * Tests rendering cell values for Excel exports.
 *
 * @coversDefaultClass \Drupal\hpc_downloads\DownloadMethods\Excel
 * @group hpc_downloads
 */
class ExcelTest extends UnitTestCase {

  /**
   * Tests rendering both standalone render arrays and table cell data.
   *
   * @covers ::renderValue
   */
  public function testRenderValueInIsolation(): void {
    $build = ['#plain_text' => 'Export & value'];
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('hasRenderContext')->willReturn(FALSE);
    $renderer->expects($this->exactly(2))
      ->method('renderInIsolation')
      ->with($build)
      ->willReturn(' <span>Export &amp; value</span> ');
    $renderer->expects($this->never())->method('render');
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    \Drupal::setContainer($container);

    $this->assertSame('Export & value', Excel::renderValue($build));
    $expected = ['data' => 'Export & value', 'colspan' => 2];
    $this->assertSame($expected, Excel::renderValue(['data' => $build, 'colspan' => 2]));
  }

}
