<?php

namespace Drupal\Tests\ghi_embargoed_access\Unit\Plugin\views\field;

use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\ghi_embargoed_access\Plugin\views\field\ProtectedStatus;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for ProtectedStatus views field plugin.
 *
 * @coversDefaultClass \Drupal\ghi_embargoed_access\Plugin\views\field\ProtectedStatus
 * @group ghi_embargoed_access
 */
class ProtectedStatusTest extends UnitTestCase {

  /**
   * The mocked embargoed access manager.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $embargoedAccessManager;

  /**
   * The views field plugin under test.
   *
   * @var \Drupal\ghi_embargoed_access\Plugin\views\field\ProtectedStatus
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
    $plugin_id = 'protected_status';
    $plugin_definition = [];

    $this->plugin = ProtectedStatus::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Tests query method does nothing.
   *
   * @covers ::query
   */
  public function testQuery(): void {
    // Query method should do nothing, just ensure it doesn't throw errors.
    $this->plugin->query();
    $this->assertTrue(TRUE);
  }

  /**
   * Tests getValue with valid node.
   *
   * @covers ::getValue
   */
  public function testGetValueWithValidNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $result_row = new ResultRow();
    $result_row->_entity = $node;

    $this->embargoedAccessManager->expects($this->once())
      ->method('isProtected')
      ->with($node)
      ->willReturn(TRUE);

    $value = $this->plugin->getValue($result_row);

    $this->assertTrue($value);
  }

  /**
   * Tests getValue with node that is not protected.
   *
   * @covers ::getValue
   */
  public function testGetValueWithUnprotectedNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $result_row = new ResultRow();
    $result_row->_entity = $node;

    $this->embargoedAccessManager->expects($this->once())
      ->method('isProtected')
      ->with($node)
      ->willReturn(FALSE);

    $value = $this->plugin->getValue($result_row);

    $this->assertFalse($value);
  }

  /**
   * Tests getValue with no entity.
   *
   * @covers ::getValue
   */
  public function testGetValueWithNoEntity(): void {
    $result_row = new ResultRow();

    $this->embargoedAccessManager->expects($this->never())
      ->method('isProtected');

    $value = $this->plugin->getValue($result_row);

    $this->assertNull($value);
  }

  /**
   * Tests getValue with non-node entity.
   *
   * @covers ::getValue
   */
  public function testGetValueWithNonNodeEntity(): void {
    $non_node_entity = new \stdClass();
    $result_row = new ResultRow();
    $result_row->_entity = $non_node_entity;

    $this->embargoedAccessManager->expects($this->never())
      ->method('isProtected');

    $value = $this->plugin->getValue($result_row);

    $this->assertNull($value);
  }

}
