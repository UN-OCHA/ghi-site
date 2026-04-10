<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * @covers Drupal\ghi_plans\Helpers\PlanEntityHelper
 */
class PlanEntityHelperTest extends UnitTestCase {

  /**
   * Data provider for testCheckObjectType.
   */
  public function checkObjectTypeDataProvider() {
    return [
      ['Plan', 'plan'],
      ['LogframeEntity', 'planEntity'],
      ['CoordinationEntity', 'governingEntity'],
      ['UnknownType', NULL],
      ['governingEntity', 'governingEntity'],
    ];
  }

  /**
   * Test checkObjectType method.
   *
   * @dataProvider checkObjectTypeDataProvider
   * @group PlanEntityHelper
   */
  public function testCheckObjectType($input, $expected) {
    $result = PlanEntityHelper::checkObjectType($input);
    $this->assertSame($expected, $result);
  }

}
