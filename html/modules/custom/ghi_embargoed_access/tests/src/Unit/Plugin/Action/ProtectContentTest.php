<?php

namespace Drupal\Tests\ghi_embargoed_access\Unit\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\ghi_embargoed_access\Plugin\Action\ProtectContent;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for ProtectContent action plugin.
 *
 * @coversDefaultClass \Drupal\ghi_embargoed_access\Plugin\Action\ProtectContent
 * @group ghi_embargoed_access
 */
class ProtectContentTest extends UnitTestCase {

  /**
   * The mocked embargoed access manager.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $embargoedAccessManager;

  /**
   * The action plugin under test.
   *
   * @var \Drupal\ghi_embargoed_access\Plugin\Action\ProtectContent
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->embargoedAccessManager = $this->createMock(EmbargoedAccessManager::class);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.manager')
      ->willReturn($this->embargoedAccessManager);

    $configuration = [];
    $plugin_id = 'protect_content';
    $plugin_definition = [];

    $this->plugin = ProtectContent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Tests execute with valid node.
   *
   * @covers ::execute
   */
  public function testExecuteWithValidNode(): void {
    $node = $this->createMock(NodeInterface::class);

    $this->embargoedAccessManager->expects($this->once())
      ->method('protectNode')
      ->with($node);

    $this->plugin->execute($node);
  }

  /**
   * Tests execute with null node.
   *
   * @covers ::execute
   */
  public function testExecuteWithNullNode(): void {
    $this->embargoedAccessManager->expects($this->never())
      ->method('protectNode');

    $this->plugin->execute(NULL);
  }

  /**
   * Tests execute with non-node object.
   *
   * @covers ::execute
   */
  public function testExecuteWithNonNode(): void {
    $not_a_node = new \stdClass();

    $this->embargoedAccessManager->expects($this->never())
      ->method('protectNode');

    $this->plugin->execute($not_a_node);
  }

  /**
   * Tests access with valid permissions.
   *
   * @covers ::access
   */
  public function testAccessWithValidPermissions(): void {
    $node = $this->createMock(NodeInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, TRUE)
      ->willReturn(AccessResult::allowed());

    $result = $this->plugin->access($node, $account, TRUE);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests access without update permission.
   *
   * @covers ::access
   */
  public function testAccessWithoutUpdatePermission(): void {
    $node = $this->createMock(NodeInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, TRUE)
      ->willReturn(AccessResult::forbidden());

    $result = $this->plugin->access($node, $account, TRUE);

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests access returning boolean.
   *
   * @covers ::access
   */
  public function testAccessReturnBoolean(): void {
    $node = $this->createMock(NodeInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, FALSE)
      ->willReturn(TRUE);

    $result = $this->plugin->access($node, $account, FALSE);

    $this->assertTrue($result);
  }

}
