<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\Facts\FactBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the fact base object.
 *
 * @group ghi_plans
 */
class FactBaseTest extends UnitTestCase {

  /**
   * Tests category field metadata and category detection.
   */
  public function testDisaggregationCategoryDetection(): void {
    $this->assertContains('GenderId', FactBase::getDisaggregationCategoryFieldNames());
    $this->assertContains('DeliveryModalityId', FactBase::getDisaggregationCategoryFieldNames());

    $total_fact_data = $this->rawFact();
    $category_fact_data = $this->rawFact([
      'GenderId' => 1,
    ]);

    $this->assertFalse(FactBase::rawFactHasDisaggregationCategories($total_fact_data));
    $this->assertTrue(FactBase::rawFactHasDisaggregationCategories($category_fact_data));
    $this->assertFalse((new AttachmentFact($total_fact_data))->hasDisaggregationCategories());
    $this->assertTrue((new AttachmentFact($category_fact_data))->hasDisaggregationCategories());
  }

  /**
   * Build a raw fact row.
   *
   * @param array $overrides
   *   Property overrides.
   *
   * @return object
   *   The raw fact row.
   */
  private function rawFact(array $overrides = []): object {
    return (object) ($overrides + [
      'Id' => 1,
      'AttachmentId' => 1001,
      'MetricTypeId' => 3001,
      'CustomMetricName' => NULL,
      'LocationId' => 10,
      'GenderId' => NULL,
      'AgeGroupId' => NULL,
      'PopulationStatusId' => NULL,
      'SettlementTypeId' => NULL,
      'DisabilityStatusId' => NULL,
      'HealthInterventionCategoryId' => NULL,
      'MaternalStatusId' => NULL,
      'DisaggregationCategoryOtherId' => NULL,
      'DeliveryModalityId' => NULL,
      'IsTotal' => TRUE,
      'ValueNum' => 0,
    ]);
  }

}
