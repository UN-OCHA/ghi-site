<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\ghi_content\ContentManager\ManagerFactory;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Plugin\QueueWorker\RemoteRefreshQueue;
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

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$node]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('node')->willReturn($storage);

    $content_manager = $this->createMock(BaseContentManager::class);
    $content_manager->expects($this->never())->method('updateNodeFromRemote');
    $content_manager->expects($this->once())->method('saveContentNode')->with($node, FALSE);

    $manager_factory = $this->createMock(ManagerFactory::class);
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
    $this->setProtectedProperty($worker, 'entityTypeManager', $entity_type_manager);
    $this->setProtectedProperty($worker, 'managerFactory', $manager_factory);
    $this->setProtectedProperty($worker, 'lock', $lock);
    $this->setProtectedProperty($worker, 'logger', $logger);

    $worker->processItem((object) [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'status' => 0,
      'event' => 'deleted',
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
