<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\ghi_blocks\Services\NodeQueue;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Layout Builder node queue service.
 *
 * @group ghi_blocks
 */
final class NodeQueueTest extends UnitTestCase {

  /**
   * Tests that current and revision tables produce unique node queue items.
   */
  public function testQueueNodesAndRevisionsForPlugin(): void {
    $current_select = $this->mockSelect([
      (object) ['entity_id' => 3],
      (object) ['entity_id' => 1],
    ]);
    $revision_select = $this->mockSelect([
      (object) ['entity_id' => 2],
      (object) ['entity_id' => 3],
    ]);

    $database = $this->createMock(Connection::class);
    $database->method('escapeLike')->willReturnArgument(0);
    $database->expects($this->exactly(2))
      ->method('select')
      ->with($this->callback(static fn (string $table): bool => in_array($table, [
        'node__layout_builder__layout',
        'node_revision__layout_builder__layout',
      ], TRUE)))
      ->willReturnOnConsecutiveCalls($current_select, $revision_select);

    $items = [];
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(static function ($item) use (&$items): int {
      $items[] = $item;
      return count($items);
    });
    $queue_factory = $this->createMock(QueueFactory::class);
    $queue_factory->expects($this->once())
      ->method('get')
      ->with('configuration_updates')
      ->willReturn($queue);

    $service = new NodeQueue($database, $queue_factory);
    $this->assertSame($queue, $service->queueNodesAndRevisionsForPlugin('example_plugin', 'configuration_updates'));
    $this->assertSame([1, 2, 3], array_column($items, 'entity_id'));
    $this->assertSame(['node', 'node', 'node'], array_column($items, 'entity_type_id'));
    $this->assertSame(['example_plugin', 'example_plugin', 'example_plugin'], array_column($items, 'plugin_id'));
  }

  /**
   * Creates a select query mock returning the given rows.
   */
  private function mockSelect(array $rows): SelectInterface {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn($rows);

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('distinct')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    return $select;
  }

}
