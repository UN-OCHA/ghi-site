<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Prototypes\PlanPrototype;

/**
 * Tests the PlanPrototype API object.
 *
 * @group ghi_plans
 */
class PlanPrototypeTest extends ApiObjectTestBase {

  /**
   * Test PlanPrototype constructor and mapping.
   */
  public function testPlanPrototypeConstructorAndMapping(): void {
    $prototype = $this->getEntityPrototypeFromFixture(4267);
    $this->assertApiObjectBasics($prototype, 'entityprototype');

    $plan_prototype = new PlanPrototype([$prototype->getRawData()]);

    // Test basic API object functionality, but skip name test since
    // PlanPrototype doesn't have a direct name.
    $this->assertInstanceOf(PlanPrototype::class, $plan_prototype);
    $this->assertEquals('planprototype', $plan_prototype->getBundle());

    $this->assertIsArray($plan_prototype->getCacheTags());

    $prototypes = $plan_prototype->getEntityPrototypes();
    $this->assertIsArray($prototypes);
    $this->assertCount(1, $prototypes);
    $this->assertEquals($prototype, reset($prototypes));

  }

}
