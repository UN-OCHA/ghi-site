<?php

namespace Drupal\Tests\ghi_plans\Kernel\Entities;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\ghi_base_objects\Kernel\BaseObjectKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests plan base objects.
 */
#[Group('ghi_plans')]
class PlanTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'ghi_plans',
  ];

  /**
   * Tests caseload selection and the plan-level data source.
   */
  public function testGetPlanCaseload() {
    $plan_type = $this->createBaseObjectType([
      'id' => 'plan',
      'field_plan_caseload' => ['type' => 'ghi_plans_plan_caseload', 'label' => 'Country Module plan caseload'],
      'field_plan_data_source' => ['type' => 'list_string', 'label' => 'Plan metric source'],
    ]);
    $plan = $this->createBaseObject([
      'type' => $plan_type->id(),
      'field_plan_caseload' => NULL,
    ]);
    $caseload_1 = $this->prophesize(CaseloadAttachmentInterface::class);
    $caseload_1->getFieldTypes()->willReturn(['inNeed', 'target']);
    $caseload_1->id()->willReturn(1);
    $caseload_2 = $this->prophesize(CaseloadAttachmentInterface::class);
    $caseload_2->getFieldTypes()->willReturn(['inNeed', 'target']);
    $caseload_2->id()->willReturn(2);
    $caseloads = [
      $caseload_1->reveal(),
      $caseload_2->reveal(),
    ];

    $this->assertNull($plan->getPlanCaseloadId());
    $this->assertEquals($caseload_1->reveal(), $plan->getPlanCaseload($caseloads));
    $this->assertSame(Plan::DATA_SOURCE_PLAN, $plan->getPlanDataSource());
    $this->assertFalse($plan->usesGhoData());

    $plan = $this->createBaseObject([
      'type' => $plan_type->id(),
      'field_plan_caseload' => 2,
      'field_plan_data_source' => Plan::DATA_SOURCE_GHO,
    ]);
    $this->assertEquals(2, $plan->getPlanCaseloadId());
    $this->assertEquals($caseload_2->reveal(), $plan->getPlanCaseload($caseloads));
    $this->assertSame(Plan::DATA_SOURCE_GHO, $plan->getPlanDataSource());
    $this->assertTrue($plan->usesGhoData());
  }

}
