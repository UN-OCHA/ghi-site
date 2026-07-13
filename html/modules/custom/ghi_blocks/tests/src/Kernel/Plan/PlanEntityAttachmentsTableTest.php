<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityAttachmentsTable;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
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
    $source_entity = $this->createSourceEntityStub(21388);
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
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment
   *   The mocked attachment.
   */
  private function mockAttachmentWithSourceEntity(int $attachment_id, PlanEntityInterface $source_entity): Attachment {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'id',
        'getSourceEntity',
        'getSourceEntityId',
        'getTitle',
        'getDescription',
        'canHaveDisaggregatedData',
      ])
      ->getMock();
    $attachment->method('id')->willReturn($attachment_id);
    $attachment->method('getSourceEntity')->willReturn($source_entity);
    $attachment->method('getSourceEntityId')->willReturn($source_entity->id());
    $attachment->method('getTitle')->willReturn('Indicator 1');
    $attachment->method('getDescription')->willReturn('Indicator description');
    $attachment->method('canHaveDisaggregatedData')->willReturn(FALSE);
    return $attachment;
  }

  /**
   * Create a source entity stub.
   *
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface
   *   The source entity.
   */
  private function createSourceEntityStub(int $entity_id): PlanEntityInterface {
    return new class((object) ['Id' => $entity_id]) extends ApiObjectBase implements PlanEntityInterface {

      /**
       * The composed reference used by the table group heading.
       *
       * @var string
       */
      private string $composedReference = 'SO1';

      /**
       * The description used by the table group heading.
       *
       * @var string
       */
      private string $description = 'Strategic objective';

      /**
       * {@inheritdoc}
       */
      public function __get($name) {
        return match ($name) {
          'composed_reference' => $this->composedReference,
          'description' => $this->description,
          default => NULL,
        };
      }

      /**
       * {@inheritdoc}
       */
      public function __isset($name) {
        return in_array($name, [
          'composed_reference',
          'description',
        ]);
      }

      /**
       * {@inheritdoc}
       */
      public function getName() {
        return 'Strategic Objective';
      }

      /**
       * {@inheritdoc}
       */
      public function getCustomName($type) {
        return 'SO1';
      }

      /**
       * {@inheritdoc}
       */
      public function getDescription(): ?string {
        return $this->description;
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityTypeRefCode() {
        return 'SO';
      }

      /**
       * {@inheritdoc}
       */
      public function getTypeName() {
        return 'Strategic objectives';
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityType() {
        return 'planEntity';
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityTypeName() {
        return 'Plan entity';
      }

      /**
       * Get the display name.
       *
       * @return string
       *   The display name.
       */
      public function getDisplayName(): string {
        return 'Strategic Objective';
      }

      /**
       * Get the sort key.
       *
       * @return string
       *   The sort key.
       */
      public function getSortKey(): string {
        return '001';
      }

    };
  }

}
