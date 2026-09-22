<?php

namespace Drupal\Tests\hpc_downloads\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Excel download preparation and value rendering.
 *
 * @coversDefaultClass \Drupal\hpc_downloads\DownloadMethods\Excel
 * @group hpc_downloads
 */
class ExcelTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests that large workbook metadata remains available without persistence.
   */
  public function testPrepareOptionsKeepsWorkbookMetadataTransient(): void {
    $commentary = str_repeat('Commentary ', 7000);
    $record = [
      'options' => [
        'type' => 'xlsx',
        'file_name' => 'test',
        'file_path' => 'public://downloads/xlsx/test.xlsx',
      ],
    ];
    $build = [
      'header' => [
        'Meta data' => [],
        'Data' => ['Value'],
      ],
      'footnotes' => [
        'Meta data' => [],
        'Data' => [[0 => $commentary]],
      ],
    ];

    $options = Excel::prepareOptions($record, $build);

    $this->assertGreaterThan(65535, strlen(serialize($options)));
    $this->assertSame($commentary, $options['footnotes'][1][0][0]);
    $this->assertStringStartsWith('temporary://test-', $options['temp_name']);
  }

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
