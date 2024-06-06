<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\hpc_common\Helpers\ContextHelper;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
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
    $entity_context_definition = $this->prophesize(EntityContextDefinition::class);

    // Mock node context.
    $node_entity = $this->prophesize(NodeInterface::class);
    $node_context = $this->prophesize(ContextInterface::class);
    $node_context->hasContextValue()->willReturn('TRUE');
    $node_context->getContextValue()->willReturn($node_entity->reveal());
    $node_context->getContextDefinition()->willReturn($entity_context_definition->reveal());

    $user_entity = $this->prophesize(UserInterface::class);
    $user_context = $this->prophesize(ContextInterface::class);
    $user_context->hasContextValue()->willReturn('TRUE');
    $user_context->getContextValue()->willReturn($user_entity->reveal());
    $user_context->getContextDefinition()->willReturn($entity_context_definition->reveal());

    return [
      [
        [
          'node' => $node_context->reveal(),
          'appeals' => 'This should not be called',
        ],
        $node_entity->reveal(),
      ],
      [
        [
          'countries' => 'Should return NULL',
        ],
        NULL,
      ],
      [
        [
          'user' => $user_context->reveal(),
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
