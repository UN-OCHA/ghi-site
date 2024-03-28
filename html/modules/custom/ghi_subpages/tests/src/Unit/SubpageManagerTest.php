<?php

namespace Drupal\Tests\ghi_subpages\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_subpages\Entity\PopulationSubpage;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages_custom\Entity\CustomSubpage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Tests the subpage manager class.
 */
class SubpageManagerTest extends UnitTestCase {

  /**
   * The subpage manager class.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * A node type representing base nodes.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $baseNodeType;

  /**
   * A node type representing standard subpage nodes.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $standardSubpageNodeType;

  /**
   * A node type representing custom subpage nodes.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $customSubpageNodeType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ghi_subpages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $base_node_type = $this->prophesize(NodeTypeInterface::class);
    $base_node_type->id()->willReturn('section');
    $this->baseNodeType = $base_node_type->reveal();

    $standard_subpage_node_type = $this->prophesize(NodeTypeInterface::class);
    $standard_subpage_node_type->id()->willReturn('population');
    $this->standardSubpageNodeType = $standard_subpage_node_type->reveal();

    $custom_subpage_node_type = $this->prophesize(NodeTypeInterface::class);
    $custom_subpage_node_type->id()->willReturn('custom_subpage');
    $this->customSubpageNodeType = $custom_subpage_node_type->reveal();

    $node_storage = $this->prophesize(EntityStorageInterface::class);
    $node_storage->loadMultiple()->willReturn([
      $this->baseNodeType,
      $this->standardSubpageNodeType,
      $this->customSubpageNodeType,
    ]);

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('node_type')->willReturn($node_storage->reveal());

    $entity_type_bundle_info = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $entity_type_bundle_info->getBundleInfo('node')->willReturn([
      'section' => ['class' => Section::class],
      'population' => ['class' => PopulationSubpage::class],
      'custom_subpage' => ['class' => CustomSubpage::class],
    ]);
    $section_manager = $this->prophesize(SectionManager::class);
    $renderer = $this->prophesize(RendererInterface::class);
    $current_user = $this->prophesize(AccountProxyInterface::class);
    $messenger = $this->prophesize(MessengerInterface::class);

    $container = new ContainerBuilder();
    $container->set('module_handler', $module_handler->reveal());
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('entity_type.bundle.info', $entity_type_bundle_info->reveal());
    $container->set('ghi_sections.manager', $section_manager->reveal());
    $container->set('renderer', $renderer->reveal());
    $container->set('current_user', $current_user->reveal());
    $container->set('messenger', $messenger->reveal());
    \Drupal::setContainer($container);

    $this->subpageManager = SubpageManager::create($container);
  }

  /**
   * Test the getStandardSubpageTypes method.
   */
  public function testGetStandardSubpageTypes() {
    $subpage_types = $this->subpageManager->getStandardSubpageTypes();
    $this->assertEquals(SubpageManager::SUPPORTED_SUBPAGE_TYPES, $subpage_types);
  }

  /**
   * Test the isBaseTypeNode method.
   */
  public function testIsBaseTypeNode() {
    $section_node = $this->prophesize(SectionNodeInterface::class);
    $non_section_node = $this->prophesize(Node::class);
    $this->assertTrue($this->subpageManager->isBaseTypeNode($section_node->reveal()));
    $this->assertFALSE($this->subpageManager->isBaseTypeNode($non_section_node->reveal()));
  }

  /**
   * Test the isManualSubpageType method.
   */
  public function testIsManualSubpageType() {
    $this->assertFalse($this->subpageManager->isManualSubpageType($this->baseNodeType));
    $this->assertFalse($this->subpageManager->isManualSubpageType($this->standardSubpageNodeType));
    $this->assertTrue($this->subpageManager->isManualSubpageType($this->customSubpageNodeType));
  }

  /**
   * Test the isSubpageType method.
   */
  public function testIsSubpageType() {
    $this->assertTrue($this->subpageManager->isSubpageType($this->standardSubpageNodeType));
    $this->assertFalse($this->subpageManager->isSubpageType($this->baseNodeType));
  }

  /**
   * Test the isStandardSubpageType method.
   */
  public function testIsStandardSubpageType() {
    $this->assertTrue($this->subpageManager->isStandardSubpageType($this->standardSubpageNodeType));
    $this->assertFalse($this->subpageManager->isStandardSubpageType($this->customSubpageNodeType));
  }

  /**
   * Test the isSubpageTypeNode method.
   */
  public function testIsSubpageTypeNode() {
    $standard_subpage_type_node = $this->prophesize(NodeInterface::class);
    $type = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $type->addMethodProphecy((new MethodProphecy($type, '__get', ['entity']))->willReturn($this->standardSubpageNodeType));
    $standard_subpage_type_node->type = $type->reveal();
    $this->assertTrue($this->subpageManager->isSubpageTypeNode($standard_subpage_type_node->reveal()));

    $non_custom_subpage_node = $this->prophesize(NodeInterface::class);
    $type = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $type->addMethodProphecy((new MethodProphecy($type, '__get', ['entity']))->willReturn($this->customSubpageNodeType));
    $non_custom_subpage_node->type = $type->reveal();
    $this->assertFalse($this->subpageManager->isSubpageTypeNode($non_custom_subpage_node->reveal()));
  }

  /**
   * Test the isStandardSubpageTypeNode method.
   */
  public function testIsStandardSubpageTypeNode() {
    $standard_subpage_type_node = $this->prophesize(NodeInterface::class);
    $type = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $type->addMethodProphecy((new MethodProphecy($type, '__get', ['entity']))->willReturn($this->standardSubpageNodeType));
    $standard_subpage_type_node->type = $type->reveal();
    $this->assertTrue($this->subpageManager->isStandardSubpageTypeNode($standard_subpage_type_node->reveal()));

    $non_custom_subpage_node = $this->prophesize(NodeInterface::class);
    $type = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $type->addMethodProphecy((new MethodProphecy($type, '__get', ['entity']))->willReturn($this->customSubpageNodeType));
    $non_custom_subpage_node->type = $type->reveal();
    $this->assertFalse($this->subpageManager->isStandardSubpageTypeNode($non_custom_subpage_node->reveal()));
  }

}
