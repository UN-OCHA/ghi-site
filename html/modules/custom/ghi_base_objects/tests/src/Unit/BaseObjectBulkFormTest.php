<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\Plugin\views\field\BaseObjectBulkForm;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests access filtering for base object bulk operations.
 */
#[Group('ghi_base_objects')]
class BaseObjectBulkFormTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests that inaccessible actions are removed from the runtime options.
   */
  public function testBulkOptionsAreFilteredByActionAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createMock(EntityInterface::class);
    $allowed_action = $this->mockAction('Allowed action', TRUE, $entity, $account);
    $denied_action = $this->mockAction('Denied action', FALSE, $entity, $account);
    $plugin = $this->mockBulkForm([
      'allowed' => $allowed_action,
      'denied' => $denied_action,
    ], $entity, $account);

    $this->assertSame(['allowed' => 'Allowed action'], $this->callPrivateMethod($plugin, 'getBulkOptions'));
  }

  /**
   * Tests that Views configuration receives every action option.
   */
  public function testUnfilteredBulkOptionsDoNotCheckAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createMock(EntityInterface::class);
    $first_action = $this->mockAction('First action', NULL);
    $second_action = $this->mockAction('Second action', NULL);
    $plugin = $this->mockBulkForm([
      'first' => $first_action,
      'second' => $second_action,
    ], $entity, $account);

    $this->assertSame([
      'first' => 'First action',
      'second' => 'Second action',
    ], $this->callPrivateMethod($plugin, 'getBulkOptions', [FALSE]));
  }

  /**
   * Creates a bulk form with the supplied actions and result entity.
   */
  private function mockBulkForm(array $actions, EntityInterface $entity, AccountInterface $account): BaseObjectBulkForm {
    $view = $this->getMockBuilder(ViewExecutable::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getUser'])
      ->getMock();
    $view->result = [new ResultRow(['_entity' => $entity])];
    $view->method('getUser')->willReturn($account);

    $plugin = $this->getMockBuilder(BaseObjectBulkForm::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $this->setPrivateProperty($plugin, 'actions', $actions);
    $this->setPrivateProperty($plugin, 'view', $view);
    $this->setPrivateProperty($plugin, 'options', [
      'include_exclude' => 'exclude',
      'selected_actions' => [],
      'relationship' => 'none',
    ]);

    return $plugin;
  }

  /**
   * Creates an action config entity with controlled access behavior.
   */
  private function mockAction(string $label, ?bool $access, ?EntityInterface $entity = NULL, ?AccountInterface $account = NULL): ActionConfigEntityInterface {
    $plugin = $this->createMock(ActionInterface::class);
    if ($access === NULL) {
      $plugin->expects($this->never())->method('access');
    }
    else {
      $plugin->expects($this->once())
        ->method('access')
        ->with($entity, $account)
        ->willReturn($access);
    }

    $action = $this->createMock(ActionConfigEntityInterface::class);
    $action->method('label')->willReturn($label);
    $action->method('getPlugin')->willReturn($plugin);
    return $action;
  }

}
