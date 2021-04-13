<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\node\Entity\Node;
use Prophecy\Argument;

use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * @covers Drupal\hpc_common\Helpers\NodeHelper
 */
class NodeHelperTest extends UnitTestCase {

  /**
   * The node helper class.
   *
   * @var \Drupal\hpc_common\Helpers\NodeHelper
   */
  protected $nodeHelper;

  /**
   * An entity object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * A node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node storage class.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The entity query class.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Mock entity.
    $this->entity = $this->prophesize(ContentEntityBase::class);

    // Mock node.
    $this->node = $this->prophesize(Node::class);

    // Mock node storage.
    $this->nodeStorage = $this->createMock(NodeStorageInterface::class);

    // Mock entity type manager.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Mock entityQuery.
    $this->entityQuery = $this->prophesize(QueryInterface::class);

    // Set container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $this->nodeHelper = new NodeHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    unset($this->nodeHelper);
    unset($this->entity);
    unset($this->entityTypeManager);
    unset($this->node);
    unset($this->nodeStorage);
    unset($this->entityQuery);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for getFieldCount.
   */
  public function getFieldCountDataProvider() {
    return [
      ['field_first_name', ['Test1', 'Test2'], 2],
      ['field_string', 'returning only string', 0],
    ];
  }

  /**
   * Test getting the field count from an entity.
   *
   * @group NodeHelper
   * @dataProvider getFieldCountDataProvider
   */
  public function testGetFieldCount($field_name, $field_value, $result) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn($field_value);
    $this->entity->hasField($field_name)->willReturn(TRUE);
    $this->entity->get($field_name)->willReturn($field->reveal());

    $this->assertEquals($result, $this->nodeHelper->getFieldCount($this->entity->reveal(), $field_name));
  }

  /**
   * Data provider for getFieldProperty.
   */
  public function getFieldPropertyDataProvider() {
    $field_data_values_array = [
      0 => [
        'last_name' => 'Gates',
        'country' => 'USA',
      ],
      1 => [
        'last_name' => 'Ambani',
        'country' => 'India',
      ],
    ];
    return [
      ['field_data', $field_data_values_array, 0, 'last_name', 'Gates'],
      ['field_data', $field_data_values_array, 1, 'country', 'India'],
      ['field_data', $field_data_values_array, 5, 'country', NULL],
      ['field_country', [], NULL, NULL, NULL],
    ];
  }

  /**
   * Test getting a field property from an entity.
   *
   * @group NodeHelper
   * @dataProvider getFieldPropertyDataProvider
   */
  public function testGetFieldProperty($field_name, $field_value, $delta, $property, $result) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn($field_value);
    $this->entity->hasField($field_name)->willReturn(TRUE);
    $this->entity->get($field_name)->willReturn($field->reveal());

    $this->assertEquals($result, $this->nodeHelper->getFieldProperty($this->entity->reveal(), $field_name, $delta, $property));
  }

  /**
   * Data provider for getNodeIdFromOriginalId.
   */
  public function getNodeIdFromOriginalIdDataProvider() {
    return [
      ['645', 'plan', '5987', TRUE],
      ['666', 'country', '7565', FALSE],
    ];
  }

  /**
   * Test getting a node if from an original id.
   *
   * @group NodeHelper
   * @dataProvider getNodeIdFromOriginalIdDataProvider
   */
  public function testGetNodeIdFromOriginalId($original_id, $bundle, $id, $return) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([$original_id]);
    $this->entity->hasField('field_original_id')->willReturn(TRUE);
    $this->entity->get('field_original_id')->willReturn($field->reveal());
    $this->entity->id()->willReturn($id);

    $entity = $return ? [$this->entity->reveal()] : [];
    $result = $return ? $id : NULL;

    // Mock loadByProperties.
    $this->nodeStorage->expects($this->any())
      ->method('loadByProperties')
      ->with([
        'type' => $bundle,
        'field_original_id' => [$original_id],
      ])
      ->willReturn($entity);

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->nodeHelper->getNodeIdFromOriginalId($original_id, $bundle));
  }

  /**
   * Data provider for getNodeFromOriginalId.
   */
  public function getNodeFromOriginalIdDataProvider() {
    return [
      ['642', 'plan', '1234', TRUE],
      ['4917', 'organization', '8454', FALSE],
    ];
  }

  /**
   * Test getting a node from an original id.
   *
   * @group NodeHelper
   * @dataProvider getNodeFromOriginalIdDataProvider
   */
  public function testGetNodeFromOriginalId($original_id, $bundle, $id, $return) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([$original_id]);
    $this->entity->hasField('field_original_id')->willReturn(TRUE);
    $this->entity->get('field_original_id')->willReturn($field->reveal());
    $this->entity->id()->willReturn($id);

    $entity = $return ? [$this->entity->reveal()] : [];
    $result = $return ? $this->entity->reveal() : NULL;

    // Mock loadByProperties.
    $this->nodeStorage->expects($this->any())
      ->method('loadByProperties')
      ->with([
        'type' => $bundle,
        'field_original_id' => [$original_id],
      ])
      ->willReturn($entity);

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->nodeHelper->getNodeFromOriginalId($original_id, $bundle));
  }

  /**
   * Data provider for getOriginalIdFromNodeId.
   */
  public function getOriginalIdFromNodeIdDataProvider() {
    return [
      ['714', '6987'],
    ];
  }

  /**
   * Test getting an original id from a node id.
   *
   * @group NodeHelper
   * @dataProvider getOriginalIdFromNodeIdDataProvider
   */
  public function testGetOriginalIdFromNodeId($original_id, $nid) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([$original_id]);
    $this->entity->hasField('field_original_id')->willReturn(TRUE);
    $this->entity->get('field_original_id')->willReturn($field->reveal());

    // Mock load.
    $this->nodeStorage->expects($this->any())
      ->method('load')
      ->with($nid)
      ->willReturn($this->entity->reveal());

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    $entity_type_repository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $entity_type_repository->getEntityTypeFromClass(Argument::any())->willReturn('node');

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository->reveal());

    $this->assertEquals($original_id, $this->nodeHelper->getOriginalIdFromNodeId($nid));
  }

  /**
   * Data provider for getTitleFromOriginalId.
   */
  public function getTitleFromOriginalIdDataProvider() {
    return [
      ['1', 'plan', TRUE, 'Mumbai 2020'],
      ['2', 'organization', TRUE, 'India'],
      ['3', 'location', FALSE, 'Delhi'],
    ];
  }

  /**
   * Test getting a node title by it's original id.
   *
   * @group NodeHelper
   * @dataProvider getTitleFromOriginalIdDataProvider
   */
  public function testGetTitleFromOriginalId($original_id, $bundle, $return, $title) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([$original_id]);

    $result = $return ? $title : NULL;

    // Set values for needed methods on node.
    $this->node->hasField('field_original_id')->willReturn(TRUE);
    $this->node->get('field_original_id')->willReturn($field->reveal());
    $this->node->getTitle()->willReturn($result);

    $entity = $return ? [$this->node->reveal()] : [];

    // Mock loadByProperties.
    $this->nodeStorage->expects($this->any())
      ->method('loadByProperties')
      ->with([
        'type' => $bundle,
        'field_original_id' => [$original_id],
      ])
      ->willReturn($entity);

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->nodeHelper->getTitleFromOriginalId($original_id, $bundle));
  }

  /**
   * Data provider for getOriginalIdFromTitle.
   */
  public function getOriginalIdFromTitleDataProvider() {
    return [
      ['Nigeria 2019', 'plan', '645', '1', ['1', '5'], '645'],
      ['Sri Lanka', 'location', '666', '2', [], NULL],
    ];
  }

  /**
   * Test getting the original id of a node by it's title.
   *
   * @group NodeHelper
   * @dataProvider getOriginalIdFromTitleDataProvider
   */
  public function testGetOriginalIdFromTitle($title, $bundle, $original_id, $nid, $query_result, $result) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([$original_id]);
    $this->entity->hasField('field_original_id')->willReturn(TRUE);
    $this->entity->get('field_original_id')->willReturn($field->reveal());

    // Mock load.
    $this->nodeStorage->expects($this->any())
      ->method('load')
      ->with($nid)
      ->willReturn($this->entity->reveal());

    // Mock entityQuery methods.
    $this->entityQuery->condition(Argument::any(), Argument::any())->willReturn($this->entityQuery);
    $this->entityQuery->execute()->willReturn($query_result);

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    // Mock getQuery.
    $this->nodeStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($this->entityQuery->reveal());

    $entity_type_repository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $entity_type_repository->getEntityTypeFromClass(Argument::any())->willReturn('node');

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository->reveal());

    $this->assertEquals($result, $this->nodeHelper->getOriginalIdFromTitle($title, $bundle));
  }

  /**
   * Test getting nodes by title.
   *
   * @group NodeHelper
   */
  public function testGetNodesFromTitle() {
    // Mock node.
    $node1 = $this->prophesize(Node::class);
    // Mock field.
    $node1_field = $this->prophesize(FieldItemListInterface::class);
    $node1_field->getValue()->willReturn([TRUE]);
    $node1->hasField('field_restricted')->willReturn(TRUE);
    $node1->get('field_restricted')->willReturn($node1_field->reveal());

    // Mock node.
    $node2 = $this->prophesize(Node::class);
    // Mock field.
    $node2_field = $this->prophesize(FieldItemListInterface::class);
    $node2_field->getValue()->willReturn([FALSE]);
    $node2->hasField('field_restricted')->willReturn(TRUE);
    $node2->get('field_restricted')->willReturn($node2_field->reveal());

    // Mock node.
    $node3 = $this->prophesize(Node::class);
    // Mock field.
    $node3_field = $this->prophesize(FieldItemListInterface::class);
    $node3_field->getValue()->willReturn([TRUE]);
    $node3->hasField('field_restricted')->willReturn(TRUE);
    $node3->get('field_restricted')->willReturn($node3_field->reveal());

    // Mock loadMultiple.
    $this->nodeStorage->expects($this->any())
      ->method('loadMultiple')
      ->with(['1', '2', '3'])
      ->willReturn([$node1->reveal(), $node2->reveal(), $node3->reveal()]);

    // Mock entityQuery methods.
    $this->entityQuery->condition(Argument::any(), Argument::any(), Argument::any())->willReturn($this->entityQuery);
    $this->entityQuery->sort(Argument::any(), Argument::any())->willReturn($this->entityQuery);
    $this->entityQuery->execute()->willReturn(['1', '2', '3']);

    // Get the nodeStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    // Mock getQuery.
    $this->nodeStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($this->entityQuery->reveal());

    $entity_type_repository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $entity_type_repository->getEntityTypeFromClass(Argument::any())->willReturn('node');

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository->reveal());

    $this->assertArrayEquals(['1' => $node2->reveal()], $this->nodeHelper->getNodesFromTitle('Test Title', 'Test bundle'));
  }

}
