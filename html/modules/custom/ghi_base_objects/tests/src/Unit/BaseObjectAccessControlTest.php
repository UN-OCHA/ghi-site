<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\BaseObjectAccessControlHandler;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the base object entity.
 */
#[Group('ghi_base_objects')]
class BaseObjectAccessControlTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

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

  /**
   * Tests that delete access uses its dedicated permission.
   */
  #[DataProvider('deleteAccessProvider')]
  public function testDeleteAccess(bool $has_permission, bool $expected): void {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with(BaseObjectAccessControlHandler::DELETE_PERMISSION)
      ->willReturn($has_permission);
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $handler = new BaseObjectAccessControlHandler($entity_type);

    $result = $this->callPrivateMethod($handler, 'checkAccess', [
      $this->createMock(EntityInterface::class),
      'delete',
      $account,
    ]);

    $this->assertSame($expected, $result->isAllowed());
    $this->assertSame(['user.permissions'], $result->getCacheContexts());
  }

  /**
   * Tests that new base objects cannot be deleted.
   */
  public function testDeleteAccessForNewEntity(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->never())->method('hasPermission');
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $handler = new BaseObjectAccessControlHandler($entity_type);

    $result = $this->callPrivateMethod($handler, 'checkAccess', [
      $entity,
      'delete',
      $account,
    ]);

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Provides dedicated delete permission outcomes.
   */
  public static function deleteAccessProvider(): array {
    return [
      'allowed' => [TRUE, TRUE],
      'denied' => [FALSE, FALSE],
    ];
  }

}
