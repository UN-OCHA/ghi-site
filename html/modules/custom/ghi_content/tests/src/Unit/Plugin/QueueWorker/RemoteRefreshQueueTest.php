<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\ghi_content\ContentManager\ManagerFactory;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Import\TargetedMigrationImporter;
use Drupal\ghi_content\Plugin\QueueWorker\RemoteRefreshQueue;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests remote refresh queue items.
 *
 * @group ghi_content
 */
class RemoteRefreshQueueTest extends UnitTestCase {

  /**
   * Tests that delete events are logged when refresh items are processed.
   */
  public function testDeleteEventIsLogged(): void {
    $node = $this->createMock(ContentBase::class);
    $node->expects($this->once())->method('setUnpublished');
    $node->method('id')->willReturn(789);

    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->once())
      ->method('loadNodesForRemoteIds')
      ->with('hpc_content_module', [123])
      ->willReturn([$node]);
    $content_manager->expects($this->never())->method('updateNodeFromRemote');
    $content_manager->expects($this->once())->method('saveContentNode')->with($node, FALSE);

    $manager_factory = $this->createMock(ManagerFactory::class);
    $manager_factory->method('getContentManagerForRemoteType')->with('article')->willReturn($content_manager);
    $manager_factory->method('getContentManager')->with($node)->willReturn($content_manager);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('ghi_content_remote_refresh:hpc_content_module:article:123');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('info')
      ->with(
        'Processed @event event for local @type node @nid from remote source @source with remote id @remote_id.',
        $this->callback(function (array $context) {
          return $context['@event'] === 'deleted'
            && $context['@type'] === 'article'
            && $context['@nid'] === 789
            && $context['@source'] === 'hpc_content_module'
            && $context['@remote_id'] === 123;
        })
      );

    $worker = new RemoteRefreshQueue([], 'ghi_content_remote_refresh', []);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'deleted',
    ]);
  }

  /**
   * Tests that missing unpublished items are not imported.
   */
  public function testMissingUnpublishedItemIsSkipped(): void {
    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->once())
      ->method('loadNodesForRemoteIds')
      ->with('hpc_content_module', [123])
      ->willReturn([]);

    $manager_factory = $this->createMock(ManagerFactory::class);
    $manager_factory->method('getContentManagerForRemoteType')->with('article')->willReturn($content_manager);
    $manager_factory->expects($this->never())->method('getContentManager');

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('ghi_content_remote_refresh:hpc_content_module:article:123');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('notice')
      ->with(
        'Remote refresh skipped for missing local @type @id from @source because @reason.',
        [
          '@type' => 'article',
          '@id' => 123,
          '@source' => 'hpc_content_module',
          '@reason' => 'the remote item is not published',
        ]
      );
    $logger->expects($this->never())->method('info');

    $worker = new RemoteRefreshQueue([], 'ghi_content_remote_refresh', []);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'trashed',
    ]);
  }

  /**
   * Tests that a missing published item is imported and processed.
   */
  public function testMissingPublishedItemIsImportedAndProcessed(): void {
    $node = $this->createMock(ContentBase::class);
    $node->method('id')->willReturn(789);

    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->exactly(2))
      ->method('loadNodesForRemoteIds')
      ->withConsecutive(
        ['hpc_content_module', [123], FALSE],
        ['hpc_content_module', [123], TRUE],
      )
      ->willReturnOnConsecutiveCalls([], [$node]);
    $content_manager->expects($this->once())->method('updateNodeFromRemote')->with($node)->willReturn(TRUE);
    $content_manager->expects($this->once())->method('saveContentNode')->with($node, FALSE);

    $manager_factory = $this->createMock(ManagerFactory::class);
    $manager_factory->method('getContentManagerForRemoteType')->with('article')->willReturn($content_manager);
    $manager_factory->method('getContentManager')->with($node)->willReturn($content_manager);

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IDLE);

    $migration_plugin_manager = $this->createMock(MigrationPluginManagerInterface::class);
    $migration_plugin_manager->method('getDefinitions')->willReturn([
      'articles_hpc_content_module' => [
        'source' => [
          'remote_source' => 'hpc_content_module',
          'content_type' => 'article',
        ],
      ],
    ]);
    $migration_plugin_manager->expects($this->once())
      ->method('createInstance')
      ->with('articles_hpc_content_module')
      ->willReturn($migration);

    $targeted_migration_importer = $this->createMock(TargetedMigrationImporter::class);
    $targeted_migration_importer->expects($this->once())
      ->method('import')
      ->with($migration, 123)
      ->willReturn(TRUE);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('ghi_content_remote_refresh:hpc_content_module:article:123');

    $info_messages = [];
    $logger = $this->createMock(LoggerInterface::class);
    $logger->method('info')->willReturnCallback(function (string $message, array $context) use (&$info_messages): void {
      $info_messages[] = [$message, $context];
    });
    $logger->expects($this->never())->method('notice');
    $logger->expects($this->never())->method('error');
    $logger->expects($this->never())->method('warning');

    $worker = new RemoteRefreshQueue([], 'ghi_content_remote_refresh', []);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'migrationPluginManager', $migration_plugin_manager);
    $this->setProtectedProperty($worker, 'targetedMigrationImporter', $targeted_migration_importer);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'saved',
    ]);

    $this->assertCount(2, $info_messages);
    $this->assertSame('Created local @type node @nid from @event event from remote source @source with remote id @remote_id.', $info_messages[0][0]);
    $this->assertSame('Processed @event event for local @type node @nid from remote source @source with remote id @remote_id.', $info_messages[1][0]);
    $this->assertSame(789, $info_messages[1][1]['@nid']);
  }

  /**
   * Tests that a busy migration delays the item for a later retry.
   */
  public function testMissingPublishedItemIsDelayedWhenMigrationIsBusy(): void {
    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->once())
      ->method('loadNodesForRemoteIds')
      ->with('hpc_content_module', [123])
      ->willReturn([]);

    $manager_factory = $this->createMock(ManagerFactory::class);
    $manager_factory->method('getContentManagerForRemoteType')->with('article')->willReturn($content_manager);
    $manager_factory->expects($this->never())->method('getContentManager');

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IMPORTING);

    $migration_plugin_manager = $this->createMock(MigrationPluginManagerInterface::class);
    $migration_plugin_manager->method('getDefinitions')->willReturn([
      'articles_hpc_content_module' => [
        'source' => [
          'remote_source' => 'hpc_content_module',
          'content_type' => 'article',
        ],
      ],
    ]);
    $migration_plugin_manager->expects($this->once())
      ->method('createInstance')
      ->with('articles_hpc_content_module')
      ->willReturn($migration);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('ghi_content_remote_refresh:hpc_content_module:article:123');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('notice')
      ->with(
        'Delayed remote refresh creating local @type @id from @source because the migration is not idle.',
        [
          '@type' => 'article',
          '@id' => 123,
          '@source' => 'hpc_content_module',
        ]
      );
    $logger->expects($this->never())->method('info');
    $logger->expects($this->never())->method('error');

    $worker = new RemoteRefreshQueue([], 'ghi_content_remote_refresh', []);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'migrationPluginManager', $migration_plugin_manager);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $this->expectException(DelayedRequeueException::class);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'saved',
    ]);
  }

  /**
   * Tests that a stale busy-migration item is not retried forever.
   */
  public function testMissingPublishedItemStopsRetryingWhenMigrationStaysBusy(): void {
    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->once())
      ->method('loadNodesForRemoteIds')
      ->with('hpc_content_module', [123])
      ->willReturn([]);

    $manager_factory = $this->createMock(ManagerFactory::class);
    $manager_factory->method('getContentManagerForRemoteType')->with('article')->willReturn($content_manager);
    $manager_factory->expects($this->never())->method('getContentManager');

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IMPORTING);

    $migration_plugin_manager = $this->createMock(MigrationPluginManagerInterface::class);
    $migration_plugin_manager->method('getDefinitions')->willReturn([
      'articles_hpc_content_module' => [
        'source' => [
          'remote_source' => 'hpc_content_module',
          'content_type' => 'article',
        ],
      ],
    ]);
    $migration_plugin_manager->expects($this->once())
      ->method('createInstance')
      ->with('articles_hpc_content_module')
      ->willReturn($migration);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(21602);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('ghi_content_remote_refresh:hpc_content_module:article:123');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Remote refresh stopped retrying local @type @id from @source because the migration stayed busy for too long.',
        [
          '@type' => 'article',
          '@id' => 123,
          '@source' => 'hpc_content_module',
        ]
      );
    $logger->expects($this->never())->method('info');
    $logger->expects($this->never())->method('notice');

    $worker = new RemoteRefreshQueue([], 'ghi_content_remote_refresh', []);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'migrationPluginManager', $migration_plugin_manager);
    $this->setProtectedProperty($worker, 'time', $time);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'saved',
      'received' => 1,
    ]);
  }

  /**
   * Set a protected property on an object.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  private function setProtectedProperty(object $object, string $property, $value): void {
    $reflection = new \ReflectionProperty($object, $property);
    $reflection->setAccessible(TRUE);
    $reflection->setValue($object, $value);
  }

}
