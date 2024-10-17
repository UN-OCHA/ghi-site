<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\UserHelper;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers Drupal\hpc_common\Helpers\UserHelper
 */
class UserHelperTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The user helper class.
   *
   * @var \Drupal\hpc_common\Helpers\UserHelper
   */
  protected $userHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock current user service.
    $current_user = $this->prophesize(AccountProxyInterface::class);

    // Mock getRoles.
    $current_user->getRoles()->willReturn(
      ['authenticated', 'editor'],
      ['authenticated', 'administrator']
    );
    // Mock hasPermission.
    $current_user->hasPermission(Argument::any())->willReturn(FALSE);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('current_user', $current_user->reveal());
    \Drupal::setContainer($container);

    $this->userHelper = new UserHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->userHelper);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Test if user is administrator method.
   *
   * @group UserHelper
   */
  public function testIsAdministrator() {
    $this->assertEquals(FALSE, $this->userHelper->isAdministrator());
    $this->assertEquals(TRUE, $this->userHelper->isAdministrator());
  }

}
