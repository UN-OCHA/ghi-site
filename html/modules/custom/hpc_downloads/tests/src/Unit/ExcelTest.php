<?php

namespace Drupal\Tests\hpc_downloads\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Excel download preparation.
 *
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

}
