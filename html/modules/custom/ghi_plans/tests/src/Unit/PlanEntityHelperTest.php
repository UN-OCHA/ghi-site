<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
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
      ['Plan', PlanEntityInterface::ENTITY_TYPE_PLAN],
      ['LogframeEntity', PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY],
      ['CoordinationEntity', PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY],
      ['UnknownType', NULL],
      [PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY, PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY],
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
