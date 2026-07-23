<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapDataFragmentBlockInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Map\MapPayload;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan attachment map block plugin.
 *
 * @group ghi_blocks
 */
class PlanAttachmentMapTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanAttachmentMap::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_attachment_map', $definition['id']);
    $this->assertEquals('Attachment Map', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Data by location', $metadata->defaultTitle);
    $this->assertArrayHasKey('attachment', $metadata->dataSources);
    $this->assertArrayHasKey('country', $metadata->dataSources);
    $this->assertArrayHasKey('entities', $metadata->dataSources);
    $this->assertArrayHasKey('location', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigValidationInterface::class, $plugin);
    $this->assertInstanceOf(LazyMapBlockInterface::class, $plugin);
    $this->assertInstanceOf(LazyMapDataFragmentBlockInterface::class, $plugin);
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

    $this->assertArrayHasKey('map', $default_config);
    $this->assertArrayHasKey('common', $default_config['map']);
    $this->assertArrayHasKey('comment', $default_config['map']['common']);
    $this->assertNull($default_config['map']['common']['comment']);
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
   * Tests the style constant.
   */
  public function testStyleConstant() {
    $this->assertEquals('circle', PlanAttachmentMap::STYLE_CIRCLE);
  }

  /**
   * Tests the empty lazy map payload.
   */
  public function testEmptyLazyMapPayload() {
    $plugin = $this->getBlockPlugin();
    $payload = $plugin->buildLazyMapPayload('test-map');

    $this->assertInstanceOf(MapPayload::class, $payload);
    $this->assertTrue($payload->isEmpty());
    $this->assertSame([], $payload->getAttachments());
    $this->assertSame('test-map', $payload->getMap()['id']);
    $this->assertSame('plan_attachment_map', $payload->getMap()['settings_key']);
  }

  /**
   * Tests that initial visibility checks avoid full map data hydration.
   */
  public function testInitialVisibilityCheckUsesMappableDataLookup(): void {
    $attachments = [
      52205 => $this->mockMappableAttachment(52205, 1263, 'Protection'),
      52202 => $this->mockMappableAttachment(52202, 1263, 'Child Protection'),
    ];
    $query = $this->getMockBuilder(AttachmentQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getAttachmentsById',
        'hasMappableDataMultiple',
        'getAttachmentDisaggregatedData',
      ])
      ->getMock();
    $query->expects($this->once())
      ->method('getAttachmentsById')
      ->with($this->callback(fn (array $ids) => $ids == [52205 => 52205, 52202 => 52202]))
      ->willReturn($attachments);
    $query->expects($this->once())
      ->method('hasMappableDataMultiple')
      ->with(
        $this->callback(fn (array $ids) => $ids == [52205 => 52205, 52202 => 52202]),
        [],
      )
      ->willReturn([
        52205 => TRUE,
        52202 => TRUE,
      ]);
    $query->expects($this->never())
      ->method('getAttachmentDisaggregatedData');

    $plugin = $this->getBlockPlugin([
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => [
              1263 => 0,
              7910 => 7910,
            ],
          ],
          'attachments' => [
            'attachment_id' => [
              52205 => 52205,
              52202 => 52202,
            ],
          ],
        ],
      ],
      'map' => [
        'common' => [
          'default_attachment' => 52205,
          'comment' => NULL,
        ],
      ],
    ], ['field_original_id' => 1263]);
    $plugin->setQueryHandler('attachment', $query);

    $this->assertFalse($plugin->isEmpty());
  }

  /**
   * Tests that configuration preview removes modal contents from map data.
   */
  public function testConfigurationPreviewMapStripsModalContents(): void {
    $plugin = $this->getBlockPlugin();
    $map = [
      'json' => [
        'people-targeted-0' => [
          'label' => 'People targeted',
          'modal_contents' => [
            '1' => ['html' => '<p>Modal</p>'],
          ],
          'variants' => [
            'f' => [
              'modal_contents' => [
                '1' => ['html' => '<p>Variant modal</p>'],
              ],
            ],
          ],
        ],
      ],
      'id' => 'test-map',
      'settings_key' => 'plan_attachment_map',
    ];

    $preview_map = $this->callPrivateMethod($plugin, 'getConfigurationPreviewMap', [$map]);

    $this->assertArrayHasKey('json', $preview_map);
    $this->assertArrayHasKey('modal_data_url', $preview_map);
    $this->assertArrayNotHasKey('modal_contents', $preview_map['json']['people-targeted-0']);
    $this->assertArrayNotHasKey('modal_contents', $preview_map['json']['people-targeted-0']['variants']['f']);
    $this->assertSame('People targeted', $preview_map['json']['people-targeted-0']['label']);
    $this->assertSame('test-map', $preview_map['id']);
    $this->assertSame('plan_attachment_map', $preview_map['settings_key']);

    $token = basename(parse_url($preview_map['modal_data_url'], PHP_URL_PATH));
    $store = $this->container->get('keyvalue.expirable')
      ->get(MapModalContent::CONFIGURATION_PREVIEW_COLLECTION);
    $base_entry = $store->get(MapModalContent::buildStoreKey($token, 'people-targeted-0', MapModalContent::DEFAULT_VARIANT_ID));
    $variant_entry = $store->get(MapModalContent::buildStoreKey($token, 'people-targeted-0', 'f'));
    $this->assertSame(['1' => ['html' => '<p>Modal</p>']], $base_entry['modal_contents']);
    $this->assertSame(['1' => ['html' => '<p>Variant modal</p>']], $variant_entry['modal_contents']);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $configuration = [], array $plan_values = []) {
    $configuration = $configuration ?: [
      'attachments' => [
        'attachment_id' => NULL,
      ],
      'map' => [
        'common' => [
          'comment' => NULL,
        ],
      ],
    ];

    $contexts = $this->getPlanSectionContexts($plan_values);

    return $this->createBlockPlugin('plan_attachment_map', $configuration, $contexts);
  }

  /**
   * Mock an attachment that can be considered for map rendering.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $plan_id
   *   The plan id.
   * @param string $description
   *   The attachment description.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment
   *   The mocked attachment.
   */
  private function mockMappableAttachment(int $attachment_id, int $plan_id, string $description): Attachment {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'id',
        'canHaveDisaggregatedData',
        'getPlanId',
        'getDescription',
      ])
      ->getMock();
    $attachment->method('id')->willReturn($attachment_id);
    $attachment->method('canHaveDisaggregatedData')->willReturn(TRUE);
    $attachment->method('getPlanId')->willReturn($plan_id);
    $attachment->method('getDescription')->willReturn($description);
    return $attachment;
  }

}
