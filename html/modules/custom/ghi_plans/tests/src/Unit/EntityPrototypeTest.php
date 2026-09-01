<?php

namespace Drupal\Tests\ghi_plans\Unit\ApiObjects;

use Drupal\Tests\ghi_plans\Unit\ApiObjectTestBase;

/**
 * Tests the EntityPrototype API object.
 *
 * @group ghi_plans
 */
class EntityPrototypeTest extends ApiObjectTestBase {

  /**
   * Test EntityPrototype for plan entities.
   */
  public function testPlanEntityPrototype(): void {
    $prototype = $this->getEntityPrototypeFromFixture('plan_entity');
    $this->assertApiObjectBasics($prototype, 'entityprototype');

    // Test EntityPrototype-specific properties.
    $this->assertEquals(4254, $prototype->id());
    $this->assertEquals('PE', $prototype->getType());
    $this->assertEquals('Strategic Objective', $prototype->getNameSingular());
    $this->assertEquals('Strategic Objectives', $prototype->getNamePlural());
    $this->assertTrue($prototype->isPlanEntity());
    $this->assertFalse($prototype->isGoverningEntity());
    $this->assertEquals('SO', $prototype->getRefCode());
    $this->assertEquals(0, $prototype->getOrderNumber());
    $this->assertIsArray($prototype->getSupportedPrototypeIds());
    $this->assertEmpty($prototype->getSupportedPrototypeIds());
    $this->assertIsArray($prototype->getChildren());
    $this->assertEmpty($prototype->getChildren());
    $this->assertIsArray($prototype->getChildrenPrototypeIds());
    $this->assertEmpty($prototype->getChildrenPrototypeIds());

    // Test another prototype with the canSupport property.
    $prototype = $this->getEntityPrototypeFromFixture('4255');
    $this->assertApiObjectBasics($prototype, 'entityprototype');
    $this->assertIsArray($prototype->getSupportedPrototypeIds());
    $this->assertCount(1, $prototype->getSupportedPrototypeIds());
  }

  /**
   * Test EntityPrototype for governing entities.
   */
  public function testGoverningEntityPrototype(): void {
    $prototype = $this->getEntityPrototypeFromFixture('governing_entity');
    $this->assertApiObjectBasics($prototype, 'entityprototype');

    // Test EntityPrototype-specific properties.
    $this->assertEquals(4267, $prototype->id());
    $this->assertEquals('GVE', $prototype->getType());
    $this->assertEquals('Cluster', $prototype->getNameSingular());
    $this->assertEquals('Clusters', $prototype->getNamePlural());
    $this->assertFalse($prototype->isPlanEntity());
    $this->assertTrue($prototype->isGoverningEntity());
    $this->assertEquals('CL', $prototype->getRefCode());
    $this->assertEquals(1, $prototype->getOrderNumber());
    $this->assertIsArray($prototype->getSupportedPrototypeIds());
    $this->assertEmpty($prototype->getSupportedPrototypeIds());
    $this->assertIsArray($prototype->getChildren());
    $this->assertCount(2, $prototype->getChildren());
    $this->assertIsArray($prototype->getChildrenPrototypeIds());
    $this->assertCount(2, $prototype->getChildrenPrototypeIds());
  }

}
