<?php

namespace Drupal\Tests\ghi_base_objects\Unit\Migrate;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ghi_base_objects\Migrate\BaseObjectMigrateBatchExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Tests recovery from interrupted base object migration batches.
 */
#[Group('ghi_base_objects')]
class BaseObjectMigrateBatchExecutableTest extends UnitTestCase {

  /**
   * The migration under test.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private MigrationInterface $migration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migration = $this->createMock(MigrationInterface::class);
    $migration_plugin_manager = $this->createMock(MigrationPluginManagerInterface::class);
    $migration_plugin_manager->method('createInstance')->with('test')->willReturn($this->migration);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('ghi_base_objects')->willReturn(new NullLogger());
    $container = new ContainerBuilder();
    $container->set('plugin.manager.migration', $migration_plugin_manager);
    $container->set('logger.factory', $logger_factory);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that an unexpected throwable releases an importing migration.
   */
  public function testThrowableResetsImportStatus(): void {
    $this->migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IMPORTING);
    $this->migration->expects($this->once())->method('setStatus')->with(MigrationInterface::STATUS_IDLE);

    try {
      $context = [];
      TestBaseObjectMigrateBatchExecutable::batchProcessImport('test', [], $context);
      $this->fail('The batch exception was not rethrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Test exception', $exception->getMessage());
    }
  }

  /**
   * Tests that recovery does not override another migration status.
   */
  public function testThrowablePreservesOtherStatus(): void {
    $this->migration->method('getStatus')->willReturn(MigrationInterface::STATUS_STOPPING);
    $this->migration->expects($this->never())->method('setStatus');

    try {
      $context = [];
      TestBaseObjectMigrateBatchExecutable::batchProcessImport('test', [], $context);
      $this->fail('The batch exception was not rethrown.');
    }
    catch (\RuntimeException) {
    }
  }

  /**
   * Tests fatal shutdown recovery and its safety guards.
   */
  public function testShutdownRecovery(): void {
    $fatal_error = ['type' => E_ERROR, 'message' => 'Maximum execution time exceeded'];
    $this->migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IMPORTING);
    $this->migration->expects($this->once())->method('setStatus')->with(MigrationInterface::STATUS_IDLE);

    TestBaseObjectMigrateBatchExecutable::recoverFromShutdownError('test', FALSE, $fatal_error);
  }

  /**
   * Tests that completed and non-fatal shutdowns leave the status untouched.
   */
  public function testSafeShutdownDoesNotResetStatus(): void {
    $this->migration->expects($this->never())->method('getStatus');
    $this->migration->expects($this->never())->method('setStatus');
    $fatal_error = ['type' => E_ERROR, 'message' => 'Maximum execution time exceeded'];
    TestBaseObjectMigrateBatchExecutable::recoverFromShutdownError('test', TRUE, $fatal_error);
    TestBaseObjectMigrateBatchExecutable::recoverFromShutdownError('test', FALSE, ['type' => E_WARNING]);
  }

}

/**
 * Exposes protected recovery methods for testing.
 */
final class TestBaseObjectMigrateBatchExecutable extends BaseObjectMigrateBatchExecutable {

  /**
   * Simulate an unexpected failure in the underlying batch operation.
   */
  protected static function processBatchOperation(string $migration_id, array $options, &$context): void {
    throw new \RuntimeException('Test exception');
  }

  /**
   * Recover an interrupted migration from shutdown error details.
   */
  public static function recoverFromShutdownError(string $migration_id, bool $operation_completed, ?array $error): void {
    parent::recoverFromShutdown($migration_id, $operation_completed, $error);
  }

}
