<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityAttachmentsTable;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan entity attachments table block plugin.
 *
 * @group ghi_blocks
 */
class PlanEntityAttachmentsTableTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanEntityAttachmentsTable::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_entity_attachments_table', $definition['id']);
    $this->assertEquals('Entity Attachments Table', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Indicator overview', $metadata->defaultTitle);
    $this->assertArrayHasKey('entities', $metadata->dataSources);
    $this->assertArrayHasKey('attachment', $metadata->dataSources);
    $this->assertArrayHasKey('attachment_prototype', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(AttachmentTableInterface::class, $plugin);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('attachments', $default_config);
    $this->assertArrayHasKey('entity_attachments', $default_config['attachments']);
    $this->assertArrayHasKey('entities', $default_config['attachments']['entity_attachments']);
    $this->assertArrayHasKey('entity_ids', $default_config['attachments']['entity_attachments']['entities']);
    $this->assertNull($default_config['attachments']['entity_attachments']['entities']['entity_ids']);

    $this->assertArrayHasKey('table', $default_config);
    $this->assertArrayHasKey('columns', $default_config['table']);
    $this->assertEmpty($default_config['table']['columns']);

    $this->assertArrayHasKey('display', $default_config);
    $this->assertArrayHasKey('table_type', $default_config['display']);
    $this->assertEquals(PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED, $default_config['display']['table_type']);
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
   * Tests the getDefaultSubform method.
   */
  public function testGetDefaultSubform() {
    $plugin = $this->getBlockPlugin();
    $default_subform = $plugin->getDefaultSubform();
    $this->assertEquals('attachments', $default_subform);
  }

  /**
   * Tests that table configuration requires a selected attachment.
   */
  public function testSubformAccessRequiresAttachments() {
    $form_state = new FormState();
    $plugin = $this->getBlockPlugin();

    $this->assertTrue($plugin->canShowSubform([], $form_state, 'attachments'));
    $this->assertFalse($plugin->canShowSubform([], $form_state, 'table'));
    $this->assertFalse($plugin->canShowSubform([], $form_state, 'display'));

    $source_entity = $this->mockSourceEntity(21388);
    $attachment = $this->mockAttachmentWithSourceEntity(38544, $source_entity);
    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachmentsById([38544])->willReturn([$attachment]);

    $plugin = $this->getBlockPlugin([
      'attachments' => [
        'entity_attachments' => [
          'attachments' => [
            'attachment_id' => [38544],
          ],
        ],
      ],
    ]);
    $plugin->setQueryHandler('attachment', $attachment_query->reveal());

    $this->assertTrue($plugin->canShowSubform([], $form_state, 'attachments'));
    $this->assertTrue($plugin->canShowSubform([], $form_state, 'table'));
    $this->assertTrue($plugin->canShowSubform([], $form_state, 'display'));
  }

  /**
   * Tests the getTitleSubform method.
   */
  public function testGetTitleSubform() {
    $plugin = $this->getBlockPlugin();
    $title_subform = $plugin->getTitleSubform();
    $this->assertEquals('display', $title_subform);
  }

  /**
   * Tests the table type constants.
   */
  public function testTableTypeConstants() {
    $this->assertEquals('grouped', PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED);
    $this->assertEquals('flat', PlanEntityAttachmentsTable::TABLE_TYPE_FLAT);
  }

  /**
   * Tests flat table grouping uses attachment source accessors.
   */
  public function testFlatTableUsesAttachmentSourceAccessors() {
    $source_entity = $this->mockSourceEntity(21388);
    $attachment = $this->mockAttachmentWithSourceEntity(38544, $source_entity);

    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachmentsById([38544])->willReturn([$attachment]);

    $plugin = $this->getBlockPlugin([
      'attachments' => [
        'prototype_id' => 5399,
        'entity_attachments' => [
          'attachments' => [
            'attachment_id' => [38544],
          ],
        ],
      ],
      'table' => [
        'columns' => [
          [
            'id' => 0,
            'item_type' => 'attachment_label',
            'config' => [
              'label' => 'Indicator',
            ],
          ],
        ],
      ],
      'display' => [
        'table_type' => PlanEntityAttachmentsTable::TABLE_TYPE_FLAT,
      ],
    ]);
    $plugin->setQueryHandler('attachment', $attachment_query->reveal());

    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');

    $this->assertIsArray($table_data);
    $this->assertCount(2, $table_data['rows']);
    $this->assertEquals('group-name', $table_data['rows'][0][0]['class']);
    $this->assertEquals('Indicator description', $table_data['rows'][1][0]['data-value']);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityAttachmentsTable
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $configuration = []) {
    $default_configuration = [
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => NULL,
          ],
          'attachments' => [
            'entity_type' => NULL,
            'attachment_type' => NULL,
            'attachment_id' => NULL,
          ],
        ],
      ],
      'table' => [
        'columns' => [],
      ],
      'display' => [
        'table_type' => PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED,
        'default_entity' => NULL,
      ],
    ];
    $configuration = array_replace_recursive($default_configuration, $configuration);

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_entity_attachments_table', $configuration, $contexts);
  }

  /**
   * Mock an attachment with one source entity.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment
   *   The mocked attachment.
   */
  private function mockAttachmentWithSourceEntity(int $attachment_id, EntityObjectInterface $source_entity): Attachment {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'id',
        'getSourceEntity',
        'getSourceEntityId',
        'getTitle',
        'getDescription',
        'canHaveDisaggregatedData',
        'getValueCacheTags',
      ])
      ->getMock();
    $attachment->method('id')->willReturn($attachment_id);
    $attachment->method('getSourceEntity')->willReturn($source_entity);
    $attachment->method('getSourceEntityId')->willReturn($source_entity->id());
    $attachment->method('getTitle')->willReturn('Indicator 1');
    $attachment->method('getDescription')->willReturn('Indicator description');
    $attachment->method('canHaveDisaggregatedData')->willReturn(FALSE);
    $attachment->method('getValueCacheTags')->willReturn(['attachment_id:' . $attachment_id]);
    return $attachment;
  }

  /**
   * Mock a source entity.
   *
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The mocked source entity.
   */
  private function mockSourceEntity(int $entity_id): EntityObjectInterface {
    $source_entity = $this->prophesize(EntityObjectInterface::class);
    $source_entity->id()->willReturn($entity_id);
    $source_entity->getComposedReference()->willReturn('SO1');
    $source_entity->getDescription()->willReturn('Strategic objective');
    $source_entity->getDisplayName()->willReturn('Strategic Objective');
    $source_entity->getSortKey()->willReturn('001');
    return $source_entity->reveal();
  }

}
