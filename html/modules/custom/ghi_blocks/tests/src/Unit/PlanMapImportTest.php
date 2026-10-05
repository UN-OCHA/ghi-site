<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCompositeMap;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\ObjectStore;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests attachment preservation when importing map configuration.
 *
 * @group ghi_blocks
 */
class PlanMapImportTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests that an attachment available on the target plan stays selected.
   *
   * @dataProvider mapPluginProvider
   */
  public function testImportKeepsAvailableAttachment(string $plugin_class): void {
    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn(1266);
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id', 'getPlanId', 'belongsToBaseObject', 'getRawData'])
      ->getMock();
    $attachment->method('id')->willReturn(52259);
    $attachment->method('getPlanId')->willReturn(1266);
    $attachment->method('belongsToBaseObject')->with($plan)->willReturn(TRUE);
    $attachment->method('getRawData')->willReturn((object) [
      'Id' => 52259,
      'PlanId' => 1266,
      'AttachmentType' => 'Caseload',
    ]);

    // Use the real query filter: a malformed filter must not silently turn an
    // available attachment into an empty selection during configuration repair.
    $object_store = $this->createMock(ObjectStore::class);
    $object_store->method('getObjects')->willReturn([52259 => $attachment]);
    $object_store->method('getObjectCollection')->willReturn([52259 => $attachment]);
    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'objectStore', $object_store);

    $entity_query = $this->createMock(EntityQuery::class);
    $entity_query->method('getEntitiesForPlan')->willReturn([]);
    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->method('createInstance')->with('plan')->willReturn($this->createMock(PlanQuery::class));

    $configuration = [
      'attachments' => [
        'entity_attachments' => [
          'entities' => ['entity_ids' => [1266 => 1266]],
          'attachments' => ['attachment_id' => [52259 => '52259']],
        ],
      ],
      'map' => ['common' => ['default_attachment' => 52259]],
    ];
    $block = $this->getMockBuilder($plugin_class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getCurrentPlanObject',
        'getCurrentBaseObject',
        'getCurrentPlanId',
        'getQueryHandler',
        'getBlockConfig',
        'setBlockConfig',
      ])
      ->getMock();
    $block->method('getCurrentPlanObject')->willReturn($plan);
    $block->method('getCurrentBaseObject')->willReturn($plan);
    $block->method('getCurrentPlanId')->willReturn(1266);
    $block->method('getQueryHandler')->willReturnMap([
      ['attachment', $attachment_query],
      ['entities', $entity_query],
    ]);
    $block->method('getBlockConfig')->willReturn($configuration);
    $block->expects($this->once())->method('setBlockConfig')->with($this->callback(function (array $updated): bool {
      $this->assertSame([52259 => 52259], $updated['attachments']['entity_attachments']['attachments']['attachment_id']);
      $this->assertSame([1266 => 1266], $updated['attachments']['entity_attachments']['entities']['entity_ids']);
      $this->assertSame(52259, $updated['map']['common']['default_attachment']);
      return TRUE;
    }));
    $this->setPrivateProperty($block, 'fabricQueryManager', $query_manager);

    $block->fixConfigErrors();
  }

  /**
   * Provides the map plugins that validate imported attachment selections.
   */
  public static function mapPluginProvider(): array {
    return [
      'composite' => [PlanCompositeMap::class],
      'attachment' => [PlanAttachmentMap::class],
    ];
  }

}
