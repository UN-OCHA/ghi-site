<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\UserHelper;
use Prophecy\Argument;

/**
 * @covers Drupal\hpc_common\Helpers\UserHelper
 */
class UserHelperTest extends UnitTestCase {

  /**
   * Mock the current user.
   */
  private function mockCurrentUser($any_permissions) {
    // Mock current user service.
    $current_user = $this->prophesize(AccountProxyInterface::class);

    // Mock getRoles.
    $current_user->getRoles()->willReturn(
      ['authenticated', 'editor'],
      ['authenticated', 'administrator']
    );
    // Mock hasPermission.
    $current_user->hasPermission(Argument::any())->willReturn($any_permissions);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('current_user', $current_user->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Test if user is administrator method.
   *
   * @group UserHelper
   */
  public function testIsAdministrator() {
    $this->mockCurrentUser(FALSE);
    $this->assertEquals(FALSE, UserHelper::isAdministrator());
    $this->assertEquals(TRUE, UserHelper::isAdministrator());

    $this->mockCurrentUser(TRUE);
    $this->assertEquals(TRUE, UserHelper::isAdministrator());
    $this->assertEquals(TRUE, UserHelper::isAdministrator());
  }

}
