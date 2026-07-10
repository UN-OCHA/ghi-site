<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;

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
   * Test attachment prototype parsing of caseload prototypes.
   */
  public function testAttachmentPrototypeCaseload() {
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

}
