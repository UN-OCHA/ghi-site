<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\BaseObjectAccessControlHandler;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;

/**
 * Tests the base object entity.
 *
 * @group ghi_base_objects
 */
class BaseObjectAccessControlTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Data provider for checkAccess.
   */
  public function baseObjectAccessControlHandlerDataProvider() {
    $entity = $this->prophesize(EntityInterface::class);
    $account = $this->prophesize(AccountInterface::class);

    return [
      [
        [$entity->reveal(), 'view label', $account->reveal()], AccessResult::allowed(),
      ],
      [
        [$entity->reveal(), 'view', $account->reveal()], AccessResult::forbidden(),
      ],
    ];
  }

  /**
   * Test BaseObjectAccessControlHandler::checkAccess.
   *
   * @dataProvider baseObjectAccessControlHandlerDataProvider
   */
  public function testBaseObjectAccessControlHandler($args, $expected) {
    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $handler = new BaseObjectAccessControlHandler($entity_type->reveal());
    $this->assertEquals($expected, $this->callPrivateMethod($handler, 'checkAccess', $args));
  }

}
