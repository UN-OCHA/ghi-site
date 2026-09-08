<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanOverviewMap;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentUnit;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests rendering fallbacks when remote data has no imported plan entity.
 *
 * @group ghi_blocks
 */
class MissingPlanEntityTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests that a unit label can be rendered without an imported plan.
   */
  public function testAttachmentUnitWithoutPlan(): void {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanObject', 'getUnitLabel'])
      ->getMock();
    $attachment->method('getPlanObject')->willReturn(NULL);
    $attachment->expects($this->once())->method('getUnitLabel')->with(NULL)->willReturn('People');

    $item = $this->getMockBuilder(AttachmentUnit::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getContextValue'])
      ->getMock();
    $item->method('getContextValue')->with('attachment')->willReturn($attachment);
    $this->assertSame('People', $item->getValue());
  }

  /**
   * Tests that the overview map falls back to the remote plan's country.
   */
  public function testOverviewLocationWithoutPlan(): void {
    $country = $this->createMock(Country::class);
    $plan = $this->createMock(PlanOverviewPlan::class);
    $plan->method('getEntity')->willReturn(NULL);
    $plan->method('getCountry')->willReturn($country);
    $map = (new \ReflectionClass(PlanOverviewMap::class))->newInstanceWithoutConstructor();

    $this->assertSame($country, $this->callPrivateMethod($map, 'getPlanLocation', [$plan]));
  }

  /**
   * Tests that an imported plan's location override is still used.
   */
  public function testOverviewLocationWithPlanOverride(): void {
    $country = $this->createMock(Country::class);
    $override = $this->createMock(Country::class);
    $entity = $this->createMock(Plan::class);
    $entity->method('getFocusCountryMapLocation')->with($country)->willReturn($override);
    $plan = $this->createMock(PlanOverviewPlan::class);
    $plan->method('getEntity')->willReturn($entity);
    $plan->method('getCountry')->willReturn($country);
    $map = (new \ReflectionClass(PlanOverviewMap::class))->newInstanceWithoutConstructor();

    $this->assertSame($override, $this->callPrivateMethod($map, 'getPlanLocation', [$plan]));
  }

  /**
   * Tests that orphaned attachments are excluded before map data is queried.
   */
  public function testAttachmentMapWithoutPlan(): void {
    drupal_static_reset();
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('getPlanObject')->willReturn(NULL);
    $attachment->method('getPlanId')->willReturn(101);
    $attachment->method('canHaveDisaggregatedData')->willReturn(TRUE);
    $query = $this->createMock(AttachmentQuery::class);
    $query->method('getAttachmentsById')->with([201])->willReturn([201 => $attachment]);
    $query->expects($this->never())->method('hasMappableDataMultiple');

    $map = $this->getMockBuilder(PlanAttachmentMap::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getUuid', 'getBlockConfig', 'getQueryHandler', 'getCurrentPlanId'])
      ->getMock();
    $map->method('getUuid')->willReturn('missing-plan-map');
    $map->method('getCurrentPlanId')->willReturn(101);
    $map->method('getBlockConfig')->willReturn([
      'attachments' => [
        'entity_attachments' => [
          'entities' => ['entity_ids' => [101]],
          'attachments' => ['attachment_id' => [201]],
        ],
      ],
    ]);
    $map->method('getQueryHandler')->with('attachment')->willReturn($query);

    $this->assertSame([], $this->callPrivateMethod($map, 'getSelectedAttachments'));
    $this->assertSame([], $this->callPrivateMethod($map, 'getSelectedAttachments'));
  }

}
