<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\Action\SetPlanDataSource;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the plan data source action.
 */
#[Group('ghi_plans')]
class SetPlanDataSourceActionTest extends UnitTestCase {

  /**
   * Tests that the configured source is saved on a plan.
   */
  public function testExecute(): void {
    $plan = $this->createMock(Plan::class);
    $plan->method('hasField')->with('field_plan_data_source')->willReturn(TRUE);
    $plan->expects($this->once())->method('set')->with('field_plan_data_source', Plan::DATA_SOURCE_GHO);
    $plan->expects($this->once())->method('save');

    $this->createAction(Plan::DATA_SOURCE_GHO)->execute($plan);
  }

  /**
   * Tests that invalid sources and non-plan objects are ignored.
   */
  public function testExecuteIgnoresInvalidTargets(): void {
    $plan = $this->createMock(Plan::class);
    $plan->method('hasField')->with('field_plan_data_source')->willReturn(TRUE);
    $plan->expects($this->never())->method('set');
    $plan->expects($this->never())->method('save');
    $this->createAction('invalid')->execute($plan);

    $base_object = $this->createMock(BaseObject::class);
    $base_object->expects($this->never())->method('set');
    $base_object->expects($this->never())->method('save');
    $this->createAction(Plan::DATA_SOURCE_GHO)->execute($base_object);
  }

  /**
   * Tests permission and bundle access for the action.
   */
  public function testAccess(): void {
    $plan = $this->createMock(Plan::class);
    $plan->method('hasField')->with('field_plan_data_source')->willReturn(TRUE);
    $allowed_account = $this->mockAccountWithPermission(TRUE);
    $denied_account = $this->mockAccountWithPermission(FALSE);
    $action = $this->createAction(Plan::DATA_SOURCE_GHO);

    $this->assertTrue($action->access($plan, $allowed_account));
    $this->assertFalse($action->access($plan, $denied_account));
    $this->assertFalse($action->access($this->createMock(BaseObject::class), $allowed_account));
    $this->assertTrue($action->access($plan, $allowed_account, TRUE)->isAllowed());
  }

  /**
   * Creates an action configured for the supplied source.
   */
  private function createAction(string $source): SetPlanDataSource {
    return new SetPlanDataSource(['source' => $source], 'set_plan_data_source', []);
  }

  /**
   * Mocks an account with controlled permission access.
   */
  private function mockAccountWithPermission(bool $allowed): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with(SetPlanDataSource::PERMISSION)->willReturn($allowed);
    return $account;
  }

}
