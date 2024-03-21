<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;

/**
 * Tests for API attachment prototype objects.
 */
class AttachmentPrototypeTest extends ApiObjectTestBase {

  /**
   * Test attachment prototype parsing of indicator prototypes.
   */
  public function testAttachmentPrototypeIndicator() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('indicator');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('Indicator', $attachment_prototype->getName());
    $this->assertEquals('indicator', $attachment_prototype->getType());
    $this->assertEquals('Indicator', $attachment_prototype->getTypeLabel());
    $this->assertCount(5, $attachment_prototype->getFields());
    $this->assertNotEmpty($attachment_prototype->getFieldTypes());
    $this->assertCount(3, $attachment_prototype->getGoalMetricFields());
    $this->assertCount(2, $attachment_prototype->getMeasurementMetricFields());
    $this->assertTrue($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['SO', 'CL', 'OC', 'OP', 'CA'], $attachment_prototype->getEntityRefCodes());
    $this->assertTrue(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of caseload prototypes.
   */
  public function testAttachmentPrototypeCaseload() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('caseload');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('Caseload', $attachment_prototype->getName());
    $this->assertEquals('caseload', $attachment_prototype->getType());
    $this->assertEquals('Caseload', $attachment_prototype->getTypeLabel());
    $this->assertCount(5, $attachment_prototype->getFields());
    $this->assertNotEmpty($attachment_prototype->getFieldTypes());
    $this->assertCount(3, $attachment_prototype->getGoalMetricFields());
    $this->assertCount(2, $attachment_prototype->getMeasurementMetricFields());
    $this->assertFalse($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['CL'], $attachment_prototype->getEntityRefCodes());
    $this->assertTrue(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of fileWebContent prototypes.
   */
  public function testAttachmentPrototypeFileWebContent() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('filewebcontent');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('File Web Content', $attachment_prototype->getName());
    $this->assertEquals('filewebcontent', $attachment_prototype->getType());
    $this->assertEquals('File (web content)', $attachment_prototype->getTypeLabel());
    $this->assertEmpty($attachment_prototype->getFields());
    $this->assertEmpty($attachment_prototype->getFieldTypes());
    $this->assertEmpty($attachment_prototype->getGoalMetricFields());
    $this->assertEmpty($attachment_prototype->getMeasurementMetricFields());
    $this->assertFalse($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['PL', 'CL'], $attachment_prototype->getEntityRefCodes());
    $this->assertFalse(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of textWebContent prototypes.
   */
  public function testAttachmentPrototypeTextWebContent() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('textwebcontent');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('Text Web Content', $attachment_prototype->getName());
    $this->assertEquals('textwebcontent', $attachment_prototype->getType());
    $this->assertEquals('Text (web content)', $attachment_prototype->getTypeLabel());
    $this->assertEmpty($attachment_prototype->getFields());
    $this->assertEmpty($attachment_prototype->getFieldTypes());
    $this->assertEmpty($attachment_prototype->getGoalMetricFields());
    $this->assertEmpty($attachment_prototype->getMeasurementMetricFields());
    $this->assertFalse($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['PL', 'CL'], $attachment_prototype->getEntityRefCodes());
    $this->assertFalse(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of contact prototypes.
   */
  public function testAttachmentPrototypeContact() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('contact');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('Contact', $attachment_prototype->getName());
    $this->assertEquals('contact', $attachment_prototype->getType());
    $this->assertEquals('Contact', $attachment_prototype->getTypeLabel());
    $this->assertEmpty($attachment_prototype->getFields());
    $this->assertEmpty($attachment_prototype->getFieldTypes());
    $this->assertEmpty($attachment_prototype->getGoalMetricFields());
    $this->assertEmpty($attachment_prototype->getMeasurementMetricFields());
    $this->assertFalse($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['CL'], $attachment_prototype->getEntityRefCodes());
    $this->assertFalse(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Test attachment prototype parsing of cost prototypes.
   */
  public function testAttachmentPrototypeCost() {
    $attachment_prototype = $this->getAttachmentPrototypeFromFixture('cost');
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment_prototype);
    $this->assertEquals('Cost', $attachment_prototype->getName());
    $this->assertEquals('cost', $attachment_prototype->getType());
    $this->assertEquals('Cost', $attachment_prototype->getTypeLabel());
    $this->assertEmpty($attachment_prototype->getFields());
    $this->assertEmpty($attachment_prototype->getFieldTypes());
    $this->assertEmpty($attachment_prototype->getGoalMetricFields());
    $this->assertEmpty($attachment_prototype->getMeasurementMetricFields());
    $this->assertFalse($attachment_prototype->isIndicator());
    $this->assertEmpty($attachment_prototype->getCalculationMethods());
    $this->assertEquals(['PL', 'CL'], $attachment_prototype->getEntityRefCodes());
    $this->assertFalse(AttachmentPrototype::isDataType($attachment_prototype->getRawData()));
  }

  /**
   * Load an attachment prototype from the fixtures.
   *
   * @param string $type
   *   The type of the attachment prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype
   *   The attachment prototype object.
   */
  private function getAttachmentPrototypeFromFixture($type) {
    $attachment_data = $this->getApiObjectFixture('AttachmentPrototype', $type);
    $this->assertNotEmpty($attachment_data);
    return new AttachmentPrototype($attachment_data);
  }

}
