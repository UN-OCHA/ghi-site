<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\ghi_form_elements\Element\AttachmentSelect;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the attachment select form element.
 *
 * @group ghi_form_elements
 */
class AttachmentSelectTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests the initial entity type selection.
   */
  public function testDefaultEntityType(): void {
    $element = new AttachmentSelect([], 'attachment_select', []);
    $options = [
      PlanEntityInterface::ENTITY_TYPE_PLAN => 'Plan',
      PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY => 'Governing entity',
      PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY => 'Plan entity',
    ];

    $cluster = $this->createMock(GoverningEntity::class);
    $this->assertSame(
      PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY,
      $this->callPrivateMethod($element, 'getDefaultEntityType', [$options, $cluster]),
    );

    $plan = $this->createMock(Plan::class);
    $this->assertSame(
      PlanEntityInterface::ENTITY_TYPE_PLAN,
      $this->callPrivateMethod($element, 'getDefaultEntityType', [$options, $plan]),
    );

    unset($options[PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY]);
    $this->assertSame(
      PlanEntityInterface::ENTITY_TYPE_PLAN,
      $this->callPrivateMethod($element, 'getDefaultEntityType', [$options, $cluster]),
    );
  }

}
