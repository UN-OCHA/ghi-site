<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormState;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\ghi_blocks\Interfaces\CustomLinkBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_subpages\LogframeManager;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan entity logframe block plugin.
 *
 * @group ghi_blocks
 */
class PlanEntityLogframeTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanEntityLogframe::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_entity_logframe', $definition['id']);
    $this->assertEquals('Entity Logframe', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertArrayHasKey('entities', $metadata->dataSources);
    $this->assertArrayHasKey('plan', $metadata->dataSources);
    $this->assertArrayHasKey('attachment', $metadata->dataSources);
    $this->assertArrayHasKey('attachment_prototype', $metadata->dataSources);
    $this->assertArrayHasKey('entity_prototype', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(CustomLinkBlockInterface::class, $plugin);
    $this->assertInstanceOf(TrustedCallbackInterface::class, $plugin);
    $this->assertInstanceOf(ConfigValidationInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurationUpdateInterface::class, $plugin);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('entities', $default_config);
    $this->assertArrayHasKey('entity_ids', $default_config['entities']);
    $this->assertNull($default_config['entities']['entity_ids']);
    $this->assertArrayHasKey('entity_ref_code', $default_config['entities']);
    $this->assertNull($default_config['entities']['entity_ref_code']);
    $this->assertArrayHasKey('id_type', $default_config['entities']);
    $this->assertNull($default_config['entities']['id_type']);
    $this->assertArrayHasKey('sort', $default_config['entities']);
    $this->assertFalse($default_config['entities']['sort']);
    $this->assertArrayHasKey('sort_column', $default_config['entities']);
    $this->assertNull($default_config['entities']['sort_column']);

    $this->assertArrayHasKey('tables', $default_config);
    $this->assertArrayHasKey('attachment_tables', $default_config['tables']);
    $this->assertEmpty($default_config['tables']['attachment_tables']);

    $this->assertArrayHasKey('display', $default_config);
    $this->assertArrayHasKey('title', $default_config['display']);
    $this->assertNull($default_config['display']['title']);
    $this->assertArrayHasKey('link', $default_config['display']);
    $this->assertNull($default_config['display']['link']);
  }

  /**
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('plan', $definition['context_definitions']);
    $this->assertArrayHasKey('plan_cluster', $definition['context_definitions']);
    $this->assertFalse($definition['context_definitions']['plan_cluster']->isRequired());
  }

  /**
   * Tests multiple methods.
   */
  public function testBaseMethods() {
    $plugin = $this->getBlockPlugin();

    $this->assertEquals('entities', $plugin->getDefaultSubform());
    $this->assertEquals('display', $plugin->getTitleSubform());
    $this->assertNull($plugin->getDefaultTitle());

    $entities = $this->callPrivateMethod($plugin, 'getRenderableEntities');
    $this->assertIsArray($entities);
    $this->assertEmpty($entities);

    $this->assertTrue($plugin->isEmpty());
    $this->assertEmpty($plugin->build());
    $this->assertNULL($plugin->buildDownloadData());

    $this->assertFalse($this->callPrivateMethod($plugin, 'hasDownloadData'));

    $plan = $this->prophesize(Plan::class);
    $plan->getPlanTypeAbbreviation()->willReturn('HRP');
    $entity = $this->prophesize(PlanEntityInterface::class);
    $id_types = [
      'custom_id' => 'Custom ID',
      'custom_id_prefixed_refcode' => 'Custom ID with refcode prefix',
      'composed_reference' => 'Composed reference',
    ];
    foreach ($id_types as $id_type => $expected) {
      $entity->getCustomName($id_type)->willReturn($expected);
      $conf = ['id_type' => $id_type];
      $this->assertSame($expected, $this->callPrivateMethod($plugin, 'getPlanEntityId', [$entity->reveal(), $conf]));
      $this->assertSame('HRP', $this->callPrivateMethod($plugin, 'getPlanEntityId', [$plan->reveal(), $conf]));
    }

    // phpcs:disable
    $description = $this->randomString(180);
    $entity->getDescription()->willReturn($description);
    $this->assertSame(Unicode::truncate($description, 120, TRUE, TRUE), $this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal(), TRUE]));
    $this->assertSame($description, $this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal(), FALSE]));
    // phpcs:enable
    $this->assertSame($description, $this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal()]));
    $entity->getDescription()->willReturn(NULL);
    $this->assertNull($this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal(), TRUE]));
    $this->assertNull($this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal(), FALSE]));
    $this->assertNull($this->callPrivateMethod($plugin, 'getPlanEntityDescription', [$entity->reveal()]));
    $this->assertIsArray($this->callPrivateMethod($plugin, 'getEntityRefCodeOptions'));
    $this->assertEmpty($this->callPrivateMethod($plugin, 'getEntityRefCodeOptions'));
    $this->assertEmpty($this->callPrivateMethod($plugin, 'getPlanEntities'));
    $this->assertIsArray($plugin::trustedCallbacks());
    $this->assertCount(2, $plugin::trustedCallbacks());
    $this->assertNull($plugin->getAttachmentsForEntities([]));

    $tables = $plugin->buildTables($entity->reveal(), []);
    $this->assertIsArray($tables);
    $this->assertEmpty($tables);
    $tables_container = $plugin->buildTablesContainer($entity->reveal(), []);
    $this->assertIsArray($tables_container);
    $this->assertEmpty($tables_container);
  }

  /**
   * Tests entity validation methods.
   */
  public function testEntityValidation() {
    $plugin = $this->getBlockPlugin();
    $entities = [];
    $invalid_entity = $this->prophesize(EntityObjectInterface::class);
    $entities[] = $invalid_entity->reveal();
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [$entities, []]);
    $this->assertIsArray($valid_entities);
    $this->assertEmpty($valid_entities);
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [[], []]);
    $this->assertIsArray($valid_entities);
    $this->assertEmpty($valid_entities);

    $invalid_entity = $this->prophesize(EntityObjectInterface::class);
    $invalid_entity->id()->willReturn(5);
    $invalid_entity->getDescription()->willReturn($this->randomString());
    $invalid_entity->getCustomName('custom_id')->willReturn(NULL);
    $entities[] = $invalid_entity->reveal();
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [$entities, []]);
    $this->assertIsArray($valid_entities);
    $this->assertEmpty($valid_entities);

    // phpcs:disable
    $valid_entity = $this->prophesize(EntityObjectInterface::class);
    $valid_entity = $this->prophesize(EntityObjectInterface::class);
    $valid_entity->id()->willReturn(1);
    $valid_entity->getDescription()->willReturn($this->randomString());
    $valid_entity->getCustomName('custom_id')->willReturn('Custom ID');
    $entities[] = $valid_entity->reveal();
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [$entities, []]);
    $this->assertIsArray($valid_entities);
    $this->assertNotEmpty($valid_entities);
    $this->assertArrayHasKey(1, $valid_entities);
    $this->assertEquals([1 => $valid_entity->reveal()], $valid_entities);
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [$entities, ['entities' => ['entity_ids' => [2, 3]]]]);
    $this->assertIsArray($valid_entities);
    $this->assertEmpty($valid_entities);
    $valid_entities = $this->callPrivateMethod($plugin, 'getValidPlanEntities', [$entities, ['entities' => ['entity_ids' => [1, 2, 3]]]]);
    $this->assertIsArray($valid_entities);
    $this->assertNotEmpty($valid_entities);
    $this->assertArrayHasKey(1, $valid_entities);
    $this->assertEquals([1 => $valid_entity->reveal()], $valid_entities);

    $entity = $this->prophesize(EntityObjectInterface::class);
    $entity->id()->willReturn(1);
    $entity->getDescription()->willReturn(NULL);
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), []]));
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [2, 3]]]]));
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [1, 2, 3]]]]));

    $entity = $this->prophesize(EntityObjectInterface::class);
    $entity->id()->willReturn(1);
    $entity->getDescription()->willReturn($this->randomString());
    $entity->getCustomName('custom_id')->willReturn(NULL);
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), []]));
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [2, 3]]]]));
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [1, 2, 3]]]]));

    $entity = $this->prophesize(EntityObjectInterface::class);
    $entity->id()->willReturn(1);
    $entity->getDescription()->willReturn($this->randomString());
    $entity->getCustomName('custom_id')->willReturn('Custom ID');
    $this->assertTrue($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), []]));
    $this->assertFalse($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [2, 3]]]]));
    $this->assertTrue($this->callPrivateMethod($plugin, 'validatePlanEntity', [$entity->reveal(), ['entities' => ['entity_ids' => [1, 2, 3]]]]));
    // phpcs:enable
  }

  /**
   * Tests multiple methods.
   */
  public function testForms() {
    $plugin = $this->getBlockPlugin();
    $form = [];
    $form_state = new FormState();

    $entities_form = $plugin->entitiesForm($form, $form_state);
    $this->assertIsArray($entities_form);
    $this->assertCount(4, $entities_form);
    $this->assertArrayHasKey('type_container', $entities_form);
    $this->assertArrayHasKey('entity_ref_code', $entities_form);
    $this->assertArrayHasKey('id_type', $entities_form);
    $this->assertArrayHasKey('actions', $entities_form);

    $tables_form = $plugin->tablesForm($form, $form_state);
    $this->assertIsArray($tables_form);
    $this->assertCount(1, $tables_form);
    $this->assertArrayHasKey('attachment_tables', $tables_form);
    $this->assertEquals('configuration_container', $tables_form['attachment_tables']['#type']);

    $display_form = $plugin->displayForm($form, $form_state);
    $this->assertIsArray($display_form);
    $this->assertCount(1, $tables_form);
    $this->assertArrayHasKey('link', $display_form);
    $this->assertEquals('custom_link', $display_form['link']['#type']);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'entities' => [
        'entity_ids' => NULL,
        'entity_ref_code' => NULL,
        'id_type' => NULL,
        'sort' => FALSE,
        'sort_column' => NULL,
      ],
      'tables' => [
        'attachment_tables' => [],
      ],
      'display' => [
        'title' => NULL,
        'link' => NULL,
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    $logframe_manager = $this->prophesize(LogframeManager::class);
    $container = \Drupal::getContainer();
    $container->set('ghi_subpages.logframe_manager', $logframe_manager->reveal());

    return $this->createBlockPlugin('plan_entity_logframe', $configuration, $contexts);
  }

}
