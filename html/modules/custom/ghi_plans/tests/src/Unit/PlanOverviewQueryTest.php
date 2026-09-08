<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan as PlanEntity;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests overview retrieval when Fabric plans have not been imported.
 *
 * @group ghi_plans
 */
class PlanOverviewQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests that only imported plans are used for overview data.
   *
   * @param int[] $fabric_ids
   *   The plan IDs returned by Fabric.
   * @param int[] $imported_ids
   *   The plan IDs with matching Drupal entities.
   *
   * @dataProvider planIdsProvider
   */
  public function testOnlyImportedPlansAreRetrieved(array $fabric_ids, array $imported_ids): void {
    drupal_static_reset();
    $plans = [];
    foreach ($fabric_ids as $id) {
      $plan = $this->createMock(Plan::class);
      $plan->method('id')->willReturn($id);
      $plan->method('getName')->willReturn('Plan ' . $id);
      $plan->method('getPlanType')->willReturn(NULL);
      $plans[$id] = $plan;
    }

    $entities = [];
    foreach ($imported_ids as $id) {
      $entity = $this->createMock(PlanEntity::class);
      $entity->method('bundle')->willReturn('plan');
      $entity->method('getSourceId')->willReturn($id);
      $entity->expects($this->once())->method('getTotalFunding')->willReturn(123.0);
      $entities[] = $entity;
    }

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($fabric_ids ? $this->once() : $this->never())
      ->method('loadByProperties')
      ->with([
        'type' => 'plan',
        'field_original_id' => array_combine($fabric_ids, $fabric_ids),
      ])
      ->willReturn($entities);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('base_object')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $plan_query = $this->createMock(PlanQuery::class);
    $plan_query->expects($this->once())->method('getPlansByYear')->with(2026)->willReturn($plans);
    $expected_ids = array_combine($imported_ids, $imported_ids);
    $prototype_query = $this->createMock(AttachmentPrototypeQuery::class);
    $prototype_query->expects($imported_ids ? $this->once() : $this->never())
      ->method('getDataPrototypesForPlans')
      ->with($expected_ids, FALSE)
      ->willReturn([]);
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $attachment_query->expects($imported_ids ? $this->once() : $this->never())
      ->method('getAttachmentsByObject')
      ->with(PlanEntityInterface::ENTITY_TYPE_PLAN, $expected_ids, ['caseload', 'cost'])
      ->willReturn([]);

    $query = new PlanOverviewQuery([], 'plan_overview', ['id' => 'plan_overview']);
    $this->setPrivateProperty($query, 'planQuery', $plan_query);
    $this->setPrivateProperty($query, 'attachmentPrototypeQuery', $prototype_query);
    $this->setPrivateProperty($query, 'attachmentQuery', $attachment_query);
    $query->setYear(2026);

    // Disable visibility filtering to verify the import guard independently.
    $result = $query->getPlans(FALSE);
    $this->assertSame($imported_ids, array_keys($result));
    foreach ($result as $plan) {
      $this->assertSame(123, $plan->getFunding());
    }
    $this->assertSame($result, $query->getPlans(FALSE));
  }

  /**
   * Provides combinations of remote and imported plans.
   *
   * @return array
   *   The plan IDs for each scenario.
   */
  public static function planIdsProvider(): array {
    return [
      'no Fabric plans' => [[], []],
      'no imported plans' => [[101, 102], []],
      'partially imported plans' => [[101, 102], [102]],
      'all plans imported' => [[101, 102], [101, 102]],
    ];
  }

}
