<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;

/**
 * Tests for API measurement objects.
 */
class MeasurementTest extends ApiObjectTestBase {

  /**
   * Tests the presence of measurement properties.
   */
  public function testMeasurementProperties() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertNotEmpty($measurement->reporting_period);
    $this->assertNotEmpty($measurement->metrics);
    $this->assertNotEmpty($measurement->totals);
    $this->assertNull($measurement->disaggregated);
    $this->assertNotEmpty($measurement->comment);
  }

  /**
   * Tests that the reporting period id is correctly retrieved.
   */
  public function testGetReportingPeriodId() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertEquals(2619, $measurement->getReportingPeriodId());
  }

  /**
   * Tests that the measurement comment is correctly retrieved.
   */
  public function testGetComment() {
    $measurement = $this->getMeasurementFromFixture('measurement');
    $this->assertEquals('The data is measured following common data collection approaches', $measurement->getComment());

    $data = $measurement->getRawData();
    $data->isCommentPublic = FALSE;

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
