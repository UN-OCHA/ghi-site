<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;

/**
 * Tests the API entity objects.
 *
 * @group ghi_plans
 */
class EntityObjectTest extends ApiObjectTestBase {

  /**
   * Test GoverningEntity constructor and basic mapping.
   */
  public function testEntityObjectBaseConstructorAndMapping(): void {
    $entity = $this->getEntityFromFixture('governing_entity');
    assert($entity instanceof GoverningEntity);

    $this->assertEquals('EDU', $entity->getCustomName('custom_id'));
    $this->assertEquals($entity->getCustomReference(), $entity->getCustomName('custom_id'));
    $this->assertEquals('CLEDU', $entity->getCustomName('custom_id_prefixed_refcode'));
    $this->assertEquals($entity->getEntityTypeRefCode() . $entity->getCustomReference(), $entity->getCustomName('custom_id_prefixed_refcode'));
    $this->assertEquals('CLEDU', $entity->getCustomName('composed_reference'));
    $this->assertEquals($entity->getComposedReference(), $entity->getCustomName('custom_id_prefixed_refcode'));
    $this->assertEquals($entity->getPluralName(), $entity->getTypeName());
    $this->assertEquals([], $entity->getChildren());
    $child = $this->prophesize(EntityObjectInterface::class);
    $child->id()->willReturn(233);
    $entity->addChild($child->reveal());
    $this->assertEquals([233 => $child->reveal()], $entity->getChildren());
    $this->assertEquals(PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY, $entity->getEntityType());
    $this->assertEquals('Governing entity', $entity->getEntityTypeName());
    $this->assertEquals(4267, $entity->getPrototypeId());
    $this->assertEquals('Cluster', $entity->getSingularName());
    $this->assertEquals('Clusters', $entity->getPluralName());
    $this->assertEquals(1, $entity->getOrderNumber());
    $this->assertEquals('1EDU', $entity->getSortKey());
    $this->assertEquals($entity->getOrderNumber() . $entity->getCustomReference(), $entity->getSortKey());
  }

  /**
   * Test GoverningEntity constructor and basic mapping.
   */
  public function testGoverningEntityConstructorAndMapping(): void {
    $entity = $this->getEntityFromFixture('governing_entity');
    assert($entity instanceof GoverningEntity);

    $this->assertEquals(7935, $entity->id());
    $this->assertIsObject($entity->getRawData());
    $this->assertIsArray($entity->toArray());
    $this->assertIsArray($entity->getCacheTags());
    $this->assertIsArray($entity->getCacheContexts());
    $this->assertIsInt($entity->getCacheMaxAge());

    $this->assertEquals('CLEDU: Education', $entity->getName());
    $this->assertEquals('CLEDU: Education', $entity->getGroupName());
    $this->assertEquals('Education', $entity->getDisplayName());
    $this->assertEquals('Cluster CLEDU: Education (EDU)', $entity->getFullName());
    $this->assertEquals(1266, $entity->getPlanId());
    $this->assertEquals('EDU', $entity->getCustomReference());
    $this->assertEquals('CLEDU', $entity->getComposedReference());
    $this->assertEquals('CL', $entity->getEntityTypeRefCode());
    $this->assertEquals('Education', $entity->getDescription());
    $this->assertEquals(['CL', 'CO', 'CA'], $entity->getValidRefCodes());
    $this->assertEquals([], $entity->getTags());
  }

  /**
   * Test PlanEntity constructor and basic mapping.
   */
  public function testPlanEntityConstructorAndMapping(): void {
    $entity = $this->getEntityFromFixture('plan_entity');
    assert($entity instanceof PlanEntity);

    $this->assertEquals(31315, $entity->id());
    $this->assertIsObject($entity->getRawData());
    $this->assertIsArray($entity->toArray());
    $this->assertIsArray($entity->getCacheTags());
    $this->assertIsArray($entity->getCacheContexts());
    $this->assertIsInt($entity->getCacheMaxAge());

    $this->assertEquals('Strategic Objective', $entity->getName());
    $this->assertEquals('Strategic Objectives', $entity->getGroupName());
    $this->assertEquals('Strategic Objective', $entity->getDisplayName());
    $this->assertEquals('Strategic Objective 1', $entity->getFullName());
    $this->assertEquals(1263, $entity->getPlanId());
    $this->assertNull($entity->getGoverningEntityParentId());
    $this->assertNull($entity->getParentId());
    $this->assertEquals([], $entity->getParentIds());
    $this->assertEquals([], $entity->getPlanEntityParents());
    $this->assertEquals(1, $entity->getCustomReference());
    $this->assertEquals('SO1', $entity->getComposedReference());
    $this->assertEquals('SO', $entity->getEntityTypeRefCode());
    $this->assertEquals('Reduce morbidity and mortality among the most vulnerable people of all genders and diversities by addressing hunger, acute malnutrition, public health threats, outbreaks, abuse, violence, and exposure to explosive ordnance.', $entity->getDescription());
    $this->assertEquals(0, $entity->getOrderNumber());
    $this->assertEquals('01', $entity->getSortKey());
    $this->assertEquals([], $entity->getTags());
  }

  /**
   * Test that plan entity sort keys include the custom reference.
   */
  public function testPlanEntitySortKeyUsesCustomReference(): void {
    $entity_data = clone $this->getApiObjectFixture('Entities', 'plan_entity');
    $entity_data->SortOrder = 4;
    $entity_data->CustomReference = '3';

    $entity = new PlanEntity($entity_data);
    (new \ReflectionClass($entity::class))->getProperty('prototype')->setValue($entity, $this->getEntityPrototypeFromFixture(4254));

    $this->assertSame('43', $entity->getSortKey());
  }

}
