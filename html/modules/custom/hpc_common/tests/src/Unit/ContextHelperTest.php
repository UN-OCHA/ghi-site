<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Annotation\ContextDefinition;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ContextHelper;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\user\UserInterface;
use Prophecy\Argument;

/**
 * @covers Drupal\hpc_common\Helpers\ContextHelper
 */
class ContextHelperTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Mock load.
    $node_entity = $this->prophesize(NodeInterface::class);
    $node_storage = $this->prophesize(NodeStorageInterface::class);
    $node_storage->load(1)->willReturn($node_entity->reveal());

    // Setup the node storage in entity type manager.
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('node')->willReturn($node_storage->reveal());

    $entity_type_repository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $entity_type_repository->getEntityTypeFromClass(Argument::any())->willReturn('node');

    // Add to container.
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('entity_type.repository', $entity_type_repository->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for getNodeFromContexts.
   */
  public function getNodeFromContextsDataProvider() {
    $entity_context_definition = $this->prophesize(EntityContextDefinition::class);
    $non_entity_context_definition = $this->prophesize(ContextDefinition::class);

    $node_entity = $this->prophesize(NodeInterface::class);

    // Mock node context.
    $node_context = $this->prophesize(ContextInterface::class);
    $node_context->hasContextValue()->willReturn('TRUE');
    $node_context->getContextValue()->willReturn($node_entity->reveal());
    $node_context->getContextDefinition()->willReturn($entity_context_definition->reveal());

    // Mock a node context that will throw an exception.
    $node_context_with_exception = $this->prophesize(ContextInterface::class);
    $node_context_with_exception->hasContextValue()->willReturn('TRUE');
    $node_context_with_exception->getContextValue()->willThrow(ContextException::class);
    $node_context_with_exception->getContextDefinition()->willReturn($entity_context_definition->reveal());

    // Mock user context.
    $user_entity = $this->prophesize(UserInterface::class);
    $user_context = $this->prophesize(ContextInterface::class);
    $user_context->hasContextValue()->willReturn('TRUE');
    $user_context->getContextValue()->willReturn($user_entity->reveal());
    $user_context->getContextDefinition()->willReturn($entity_context_definition->reveal());

    // Mock invalid context.
    $entity = $this->prophesize(EntityInterface::class);
    $entity_context = $this->prophesize(ContextInterface::class);
    $entity_context->hasContextValue()->willReturn('TRUE');
    $entity_context->getContextValue()->willReturn($entity->reveal());
    $entity_context->getContextDefinition()->willReturn($non_entity_context_definition->reveal());

    $node_context_scalar = $this->prophesize(ContextInterface::class);
    $node_context_scalar->hasContextValue()->willReturn('TRUE');
    $node_context_scalar->getContextValue()->willReturn(1);
    $node_context_scalar->getContextDefinition()->willReturn($entity_context_definition->reveal());

    return [
      [['node' => $node_context->reveal(), 'appeals' => 'Not called'], $node_entity->reveal()],
      [['node' => $node_context_with_exception->reveal()], NULL],
      [['countries' => 'Should return NULL'], NULL],
      [['user' => $user_context->reveal()], NULL],
      [['entity' => $entity_context->reveal()], NULL],
      [['node' => $node_context_scalar->reveal()], $node_entity->reveal()],
    ];
  }

  /**
   * Test getting node from contexts.
   *
   * @group ContextHelper
   * @dataProvider getNodeFromContextsDataProvider
   */
  public function testGetNodeFromContexts($contexts, $result) {
    $this->assertEquals($result, ContextHelper::getNodeFromContexts($contexts));
  }

}
