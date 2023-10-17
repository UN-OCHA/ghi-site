<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\hpc_common\Helpers\ContextHelper;
use Drupal\node\Entity\Node;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers Drupal\hpc_common\Helpers\ContextHelper
 */
class ContextHelperTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The context helper class.
   *
   * @var \Drupal\hpc_common\Helpers\ContextHelper
   */
  protected $contextHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contextHelper = new ContextHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->contextHelper);
  }

  /**
   * Data provider for getNodeFromContexts.
   */
  public function getNodeFromContextsDataProvider() {
    // Mock node context.
    $node_entity = $this->prophesize(Node::class)->reveal();
    $node_context = $this->prophesize(ContextInterface::class);
    $node_context->hasContextValue()->willReturn('TRUE');
    $node_context->getContextValue()->willReturn($node_entity);
    $node_result = $node_context->reveal();

    return [
      [
        [
          'node' => $node_result,
          'appeals' => 'This should not be called',
        ],
        $node_entity,
      ],
      [
        [
          'countries' => 'Should return NULL',
        ],
        NULL,
      ],
    ];
  }

  /**
   * Test getting node from contexts.
   *
   * @group ContextHelper
   * @dataProvider getNodeFromContextsDataProvider
   */
  public function testGetNodeFromContexts($contexts, $result) {
    $this->assertEquals($result, $this->contextHelper->getNodeFromContexts($contexts));
  }

}
