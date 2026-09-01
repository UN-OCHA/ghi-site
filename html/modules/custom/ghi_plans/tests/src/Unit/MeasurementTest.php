<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;

/**
 * Tests for API measurement objects.
 */
class MeasurementTest extends ApiObjectTestBase {

  /**
   * Tests the presence of measurement properties.
   */
  public function testMeasurementProperties() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertNotEmpty($measurement->getReportingPeriodId());
    $this->assertNotEmpty($measurement->getValues());
    $this->assertNotEmpty($measurement->getTotals());
    $this->assertNotEmpty($measurement->getPlanId());
    $this->assertNotEmpty($measurement->getSourceEntityId());
    $this->assertNotEmpty($measurement->getSourceEntityType());
    $this->assertIsObject($measurement->getDisaggregated());
    $this->assertEmpty($measurement->getDisaggregated()->locations);
    $this->assertEmpty($measurement->getDisaggregated()->categories);
    $this->assertEmpty($measurement->getDisaggregated()->metrics);
    $this->assertNotEmpty($measurement->getComment());
    $this->assertNotEmpty($measurement->getPrototype());
  }

  /**
   * Tests that the reporting period id is correctly retrieved.
   */
  public function testGetReportingPeriodId() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertEquals(2619, $measurement->getReportingPeriodId());
  }

  /**
   * Tests that the reporting period id is correctly retrieved.
   */
  public function testGetPrototype() {
    $prototype = $this->prophesize(AttachmentPrototype::class)->reveal();
    $measurement = $this->getMeasurementFromFixture('measurement');

    // Prototype is fecthed.
    $this->assertNotEmpty($measurement->getPrototype());
    $this->assertNotSame($prototype, $measurement->getPrototype());

    // Manually set a prototype and check that this is returned.
    $this->setPrivateProperty($measurement, 'prototype', $prototype);
    $this->assertNotEmpty($measurement->getPrototype());
    $this->assertSame($prototype, $measurement->getPrototype());

    // No queries, so we expect an exception.
    $this->disableFabricQueries();
    $this->setPrivateProperty($measurement, 'prototype', NULL);
    $this->assertNull($this->getPrivateProperty($measurement, 'prototype'));
    $this->expectExceptionMessageMatches('/Failed to extract prototype for attachment [\d+]/');
    $this->assertNull($measurement->getPrototype());
  }

  /**
   * Tests that the measurement comment is correctly retrieved.
   */
  public function testGetComment() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertEquals('The data is measured following common data collection approaches', $measurement->getComment());

    $data = $measurement->getRawData();
    $data->IsCommentPublic = FALSE;

    $measurement = new Measurement($data);
    $this->assertEquals(NULL, $measurement->getComment());
  }

  /**
   * Load a measurement object from the fixtures.
   *
   * @param string $name
   *   The name of the measurement fixture.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface
   *   The measurement object.
   */
  private function getMeasurementFromFixture($name) {
    $measurement_data = $this->getApiObjectFixture('Measurements', $name);
    $this->assertNotEmpty($measurement_data);
    return new Measurement($measurement_data);
  }

}
