<?php

namespace Drupal\Tests\hpc_downloads\Kernel;

use Drupal\hpc_downloads\DownloadRecord;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests persistence of download progress and completion.
 */
#[CoversClass(DownloadRecord::class)]
#[Group('hpc_downloads')]
class DownloadRecordTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'hpc_downloads'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('hpc_downloads', ['hpc_download_processes']);
  }

  /**
   * Tests that updates and completion change only the intended record.
   */
  #[DataProvider('downloadRecordProvider')]
  public function testRecordLifecycle(string $format, int $status): void {
    $options = ['format' => $format, 'nested' => ['label' => 'Test download']];
    $record = DownloadRecord::createRecord('/test-download', $options);
    $other = DownloadRecord::createRecord('/other-download', $options);
    $id = $record['id'];
    $options['database_target'] = DownloadRecord::getDatabase()->getTarget();
    $this->assertSame($options, $record['options']);

    $record['status'] = DownloadRecord::STATUS_PENDING;
    $record['message'] = 'Preparing download';
    DownloadRecord::updateRecord($record);
    $this->assertEquals($id, $record['id']);
    $this->assertEquals(DownloadRecord::STATUS_PENDING, $record['status']);
    $this->assertSame('Preparing download', $record['message']);
    $this->assertSame($options, $record['options']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $record['updated']);

    // Callers can pass either decoded options or their stored representation.
    $record['options'] = serialize($options);
    $record['file_path'] = 'public://downloads/test.' . $format;
    DownloadRecord::updateRecord($record);
    $this->assertSame($options, $record['options']);
    $record['options'] = serialize($options);
    DownloadRecord::closeRecord($record, $status);
    $this->assertEquals($status, $record['status']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $record['completed']);
    $this->assertSame('public://downloads/test.' . $format, $record['file_path']);
    $this->assertSame($options, $record['options']);
    $this->assertSame($record, DownloadRecord::loadRecordById($id));
    $this->assertSame($other, DownloadRecord::loadRecordById($other['id']));
    $this->assertCount(2, DownloadRecord::loadRecords());
  }

  /**
   * Provides formats and completion states that share the record lifecycle.
   */
  public static function downloadRecordProvider(): array {
    return [
      'successful spreadsheet' => ['xlsx', DownloadRecord::STATUS_SUCCESS],
      'failed PDF' => ['pdf', DownloadRecord::STATUS_ERROR],
    ];
  }

}
