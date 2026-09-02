<?php

namespace Drupal\Tests\ghi_plans\Kernel\Entities;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\Tests\ghi_base_objects\Kernel\BaseObjectKernelTestBase;

/**
 * Tests the API entity objects.
 *
 * @group ghi_plans
 */
class PlanTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ghi_plans',
  ];

  /**
   * Test the getPlanCaseloadId and getPlanCaseload methods.
   */
  public function testGetPlanCaseload() {
    $plan_type = $this->createBaseObjectType([
      'id' => 'plan',
      'field_plan_caseload' => ['type' => 'ghi_plans_plan_caseload', 'label' => 'Plan caseload'],
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

    $plan = $this->createBaseObject([
      'type' => $plan_type->id(),
      'field_plan_caseload' => 2,
    ]);
    $this->assertEquals(2, $plan->getPlanCaseloadId());
    $this->assertEquals($caseload_2->reveal(), $plan->getPlanCaseload($caseloads));
  }

}
