<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Plugin\FabricQuery\EntityTypeQuery;

/**
 * Tests for API attachment prototype objects.
 */
class AttachmentPrototypeTest extends ApiObjectTestBase {

  /**
   * Test attachment prototype parsing of indicator prototypes.
   */
  public function testAttachmentPrototypeIndicator() {
    $prototype = $this->getAttachmentPrototypeFromFixture('indicator');
    $this->assertInstanceOf(AttachmentPrototype::class, $prototype);
    $this->assertEquals('Indicator', $prototype->getName());
    $this->assertEquals('indicator', $prototype->getType());
    $this->assertEquals('Indicator', $prototype->getTypeLabel());
    $this->assertEquals('IN', $prototype->getRefCode());
    $this->assertCount(3, $prototype->getFields());
    $this->assertNotEmpty($prototype->getFieldTypes());
    $this->assertCount(1, $prototype->getPlanningFields());
    $this->assertCount(2, $prototype->getMeasurementFields());
    $this->assertEmpty($prototype->getCalculatedFields());
    $this->assertTrue($prototype->isIndicator());
    $this->assertCount(5, $prototype->getCalculationMethods());
    $this->assertEquals(['SO', 'CO', 'CA'], $prototype->getEntityRefCodes());
    $this->assertTrue(AttachmentPrototype::isDataType($prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of configured labels.
   */
  public function testAttachmentPrototypeConfiguredLabel() {
    $prototype = $this->getAttachmentPrototypeFromFixture('5399');
    $this->assertEquals('Indicateur', $prototype->getName());
    $this->assertEquals('Indicator', $prototype->getTypeLabel());
  }

  /**
   * Test metric type matching for duplicate prototype field types.
   */
  public function testAttachmentPrototypeDuplicateMeasureFields() {
    $this->setMetricTypes([
      $this->createMetricType(21, 'Measure', 'measure', 'Measure|Mesure|Medida'),
      $this->createMetricType(22, 'Measure Cumulative', 'cumulativeMeasure', 'Measure (cumulative)|Cumulative measure'),
      $this->createMetricType(31, 'Reached', 'measure|reached', 'measure|Reached|Alcanzado'),
    ]);

    $prototype = new AttachmentPrototype((object) [
      'Id' => 5440,
      'RefCode' => 'IN',
      'Type' => 'indicator',
      'Value' => (object) [
        'measureFields' => [
          (object) [
            'name' => (object) ['en' => 'Measure'],
            'type' => 'measure',
          ],
          (object) [
            'name' => (object) ['en' => 'Cumulative measure'],
            'type' => 'measure',
          ],
        ],
        'metrics' => [
          (object) [
            'name' => (object) ['en' => 'Target'],
            'type' => 'target',
          ],
        ],
        'name' => (object) ['en' => 'Indicator'],
        'entities' => [],
      ],
      'PlanId' => 1117,
      'CreatedAt' => '2022-09-28T10:09:09.000Z',
      'UpdatedAt' => '2024-09-13T17:07:39.000Z',
    ]);

    $this->assertSame([
      'target',
      'measure',
      'cumulative_measure',
    ], $prototype->getFieldTypes());
  }

  /**
   * Test duplicate raw fields keep their original index definitions.
   */
  public function testAttachmentPrototypeOriginalFieldDefinitions() {
    $this->setMetricTypes([
      $this->createMetricType(14, 'Cumulative reach', 'cumulativeReach', 'Cumulative reach|Personas atendidas (acumulativo)'),
      $this->createMetricType(30, 'Latest reached', 'latestReach', 'Latest Reached|People reached'),
    ]);

    $prototype = new AttachmentPrototype((object) [
      'Id' => 5440,
      'RefCode' => 'BP',
      'Type' => 'caseload',
      'Value' => (object) [
        'measureFields' => [
          (object) [
            'name' => (object) ['en' => 'Personas atendidas (acumulativo)'],
            'type' => 'cumulativeReach',
          ],
          (object) [
            'name' => (object) ['en' => 'Personas atendidas (acumulativo)'],
            'type' => 'cumulativeReach',
          ],
          (object) [
            'name' => (object) ['en' => 'Latest Reached'],
            'type' => 'latestReach',
          ],
        ],
        'metrics' => [
          (object) [
            'name' => (object) ['en' => 'Target'],
            'type' => 'target',
          ],
        ],
        'name' => (object) ['en' => 'Caseload'],
        'entities' => [],
      ],
      'PlanId' => 1090,
      'CreatedAt' => '2022-09-28T10:09:09.000Z',
      'UpdatedAt' => '2024-09-13T17:07:39.000Z',
    ]);

    $this->assertSame([
      'target',
      'cumulative_reach',
      'latest_reach',
    ], $prototype->getFieldTypes());
    $this->assertCount(4, $prototype->getFieldDefinitions());
    $this->assertSame('cumulative_reach', $prototype->getMetricTypeByOriginalIndex(1));
    $this->assertSame('cumulative_reach', $prototype->getMetricTypeByOriginalIndex(2));
    $this->assertSame('latest_reach', $prototype->getMetricTypeByOriginalIndex(3));
    $this->assertSame(3, $prototype->getOriginalIndexByMetricType('latest_reach'));
  }

  /**
   * Test attachment prototype parsing of caseload prototypes.
   */
  public function testAttachmentPrototypeCaseload() {
    $this->setMetricTypes([
      $this->createMetricType(31, 'Reached', 'measure|reached', 'Reached|Atteints|Personas Atendidas'),
      $this->createMetricType(20, 'Covered', 'covered', 'Covered|Couverts|Personas con Necesidades Cubiertas'),
    ]);

    $prototype = $this->getAttachmentPrototypeFromFixture('caseload');
    $this->assertInstanceOf(AttachmentPrototype::class, $prototype);
    $this->assertEquals('Caseload', $prototype->getName());
    $this->assertEquals('caseload', $prototype->getType());
    $this->assertEquals('Caseload', $prototype->getTypeLabel());
    $this->assertEquals('BF', $prototype->getRefCode());
    $this->assertCount(5, $prototype->getFields());
    $this->assertNotEmpty($prototype->getFieldTypes());
    $this->assertCount(5, $prototype->getOriginalFields());
    $this->assertCount(3, $prototype->getPlanningFields());
    $this->assertCount(2, $prototype->getMeasurementFields());
    $this->assertEmpty($prototype->getCalculatedFields());
    $this->assertFalse($prototype->isIndicator());
    $this->assertEmpty($prototype->getCalculationMethods());
    $this->assertEquals(['CL'], $prototype->getEntityRefCodes());
    $this->assertEquals('People reached', (string) $prototype->getDefaultFieldLabel('cumulative_reach'));
    $this->assertEquals('Measure', (string) $prototype->getDefaultFieldLabel('periodical_measure'));
    $this->assertEquals('Measure', (string) $prototype->getDefaultFieldLabel('cumulative_measure'));
    $this->assertEquals(NULL, (string) $prototype->getDefaultFieldLabel('invalid_metric_type'));
    $this->assertTrue(AttachmentPrototype::isDataType($prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of cost prototypes.
   */
  public function testAttachmentPrototypeCost() {
    $prototype = $this->getAttachmentPrototypeFromFixture('cost');
    $this->assertInstanceOf(AttachmentPrototype::class, $prototype);
    $this->assertEquals('Cost', $prototype->getName());
    $this->assertEquals('cost', $prototype->getType());
    $this->assertEquals('Cost', $prototype->getTypeLabel());
    $this->assertEquals('CS', $prototype->getRefCode());
    $this->assertEmpty($prototype->getFields());
    $this->assertEmpty($prototype->getFieldTypes());
    $this->assertEmpty($prototype->getPlanningFields());
    $this->assertEmpty($prototype->getMeasurementFields());
    $this->assertEmpty($prototype->getCalculatedFields());
    $this->assertFalse($prototype->isIndicator());
    $this->assertEmpty($prototype->getCalculationMethods());
    $this->assertEquals(['PL', 'CL'], $prototype->getEntityRefCodes());
    $this->assertFalse(AttachmentPrototype::isDataType($prototype->getRawData()));
  }

  /**
   * Load an attachment prototype from the fixtures.
   *
   * @param string $type
   *   The type of the attachment prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype
   *   The attachment prototype object.
   */
  private function getAttachmentPrototypeFromFixture($type) {
    $data = $this->getApiObjectFixture('AttachmentPrototype', $type);
    $this->assertNotEmpty($data);
    return new AttachmentPrototype($data);
  }

  /**
   * Create a metric type object.
   *
   * @param int $id
   *   The metric type id.
   * @param string $name
   *   The metric type name.
   * @param string $hpc_type
   *   The HPC type.
   * @param string $label_lookup
   *   The label lookup string.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType
   *   The metric type object.
   */
  private function createMetricType(int $id, string $name, string $hpc_type, string $label_lookup): MetricType {
    return new MetricType((object) [
      'Id' => $id,
      'Name' => $name,
      'HPCType' => $hpc_type,
      'LabelLookup' => $label_lookup,
    ]);
  }

  /**
   * Set the metric types returned by the mocked entity type query.
   *
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType[] $metric_types
   *   The metric type objects.
   */
  private function setMetricTypes(array $metric_types): void {
    $entity_type_query = $this->prophesize(EntityTypeQuery::class);
    $entity_type_query->getMetricTypes()->willReturn($metric_types);

    $fabric_query_manager = $this->prophesize('Drupal\hpc_api\Query\FabricQueryManager');
    $fabric_query_manager->hasDefinition('entity_type')->willReturn(TRUE);
    $fabric_query_manager->createInstance('entity_type')->willReturn($entity_type_query->reveal());

    $container = \Drupal::getContainer();
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    \Drupal::setContainer($container);
    drupal_static_reset();
  }

}
