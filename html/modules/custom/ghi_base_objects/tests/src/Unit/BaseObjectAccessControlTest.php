<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\BaseObjectAccessControlHandler;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use PHPUnit\Framework\Attributes\DataProvider;

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
  public static function baseObjectAccessControlHandlerDataProvider() {
    return [
      [
        'view label', AccessResult::allowed(),
      ],
      [
        'view', AccessResult::forbidden(),
      ],
    ];
  }

  /**
   * Test BaseObjectAccessControlHandler::checkAccess.
   */
  #[DataProvider('baseObjectAccessControlHandlerDataProvider')]
  public function testBaseObjectAccessControlHandler($operation, $expected) {
    $args = [$this->createMock(EntityInterface::class), $operation, $this->createMock(AccountInterface::class)];
    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $handler = new BaseObjectAccessControlHandler($entity_type->reveal());
    $this->assertEquals($expected, $this->callPrivateMethod($handler, 'checkAccess', $args));
  }

}
