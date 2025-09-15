<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\EntityPrototype;

/**
 * Tests the EntityPrototype API object.
 *
 * @group ghi_plans
 */
class EntityPrototypeTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'migrate',
    'ghi_base_objects',
    'ghi_plans',
    'hpc_api',
    'hpc_common',
  ];

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $entity_prototype_defaults = [
      'refCode' => 'REF' . rand(1, 100),
      'type' => (object) ['name' => 'Entity Type'],
      'value' => (object) [
        'name' => (object) [
          'en' => (object) [
            'singular' => 'Entity',
            'plural' => 'Entities',
          ],
        ],
        'canSupport' => [],
        'possibleChildren' => [],
      ],
      'orderNumber' => rand(1, 10),
    ];

    $merged_overrides = array_merge($entity_prototype_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test EntityPrototype constructor and mapping.
   */
  public function testEntityPrototypeConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'refCode' => 'REF123',
      'type' => (object) ['name' => 'Plan Entity'],
      'value' => (object) [
        'name' => (object) [
          'en' => (object) [
            'singular' => 'Entity Prototype',
            'plural' => 'Entity Prototypes',
          ],
        ],
        'canSupport' => [],
        'possibleChildren' => [],
      ],
      'orderNumber' => 1,
    ]);

    $entity_prototype = new EntityPrototype($raw_data);

    $this->assertApiObjectBasics($entity_prototype, 'entityprototype');

    // Test EntityPrototype-specific properties.
    $this->assertEquals(123, $entity_prototype->id());
    $this->assertEquals('Entity Prototype', $entity_prototype->getNameSingular());
    $this->assertEquals('Entity Prototypes', $entity_prototype->getNamePlural());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(EntityPrototype::class);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $entity_prototype = new EntityPrototype($raw_data);

    $cache_tags = $entity_prototype->getCacheTags();
    $this->assertIsArray($cache_tags);
  }

}
