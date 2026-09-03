<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CompositeMap;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Tests the composite map configuration item plugin.
 *
 * @group ghi_blocks
 */
class CompositeMapTest extends UnitTestCase {

  /**
   * Tests configuration feedback for an unavailable slice metric.
   */
  public function testUnavailableSliceMetricFeedback(): void {
    $composite_map = $this->createCompositeMap([
      'base' => ['in_need', 'total_population'],
      'measurements' => [],
    ]);
    $composite_map->setConfig([
      'label' => 'People reached',
      'dataset_form' => [
        'datasets' => [
          'full_pie' => [
            'attachment' => 51955,
            'metric' => 'in_need',
            'settings' => [],
          ],
          'polygon' => [
            'attachment' => 51955,
            'metric' => 'total_population',
            'settings' => [],
          ],
          'slices' => [
            [
              'attachment' => 51955,
              'metric' => 'expected_reach',
              'settings' => [],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame([
      'No location-level data is available for Expected reach. This dataset will not appear on the map.',
    ], array_map('strval', $composite_map->getConfigurationErrors()));
  }

  /**
   * Tests configuration feedback for an unavailable full pie metric.
   */
  public function testUnavailableFullPieMetricFeedback(): void {
    $composite_map = $this->createCompositeMap([
      'base' => ['in_need'],
      'measurements' => [],
    ]);
    $composite_map->setConfig([
      'label' => 'Affected people',
      'dataset_form' => [
        'datasets' => [
          'full_pie' => [
            'attachment' => 51955,
            'metric' => 'affected',
            'settings' => [],
          ],
          'polygon' => NULL,
          'slices' => [],
        ],
      ],
    ]);

    $this->assertSame([
      'No location-level data is available for Affected people. The map will not be displayed.',
    ], array_map('strval', $composite_map->getConfigurationErrors()));
  }

  /**
   * Create a composite map configuration item plugin.
   */
  private function createCompositeMap(array $availability): CompositeMap {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('id')->willReturn(51955);
    $attachment->method('getFieldTypes')->willReturn([
      'total_population',
      'affected',
      'in_need',
      'expected_reach',
    ]);
    $attachment->method('getFields')->willReturn([
      'total_population' => 'Population',
      'affected' => 'Affected people',
      'in_need' => 'People in need',
      'expected_reach' => 'Expected reach',
    ]);
    $attachment->method('isMeasurementField')->willReturn(FALSE);

    $attachment_query = $this->createMock(AttachmentQuery::class);
    $attachment_query->method('getAttachment')->with(51955)->willReturn($attachment);
    $attachment_query->method('getMappableMapMetricAvailability')->with($attachment)->willReturn($availability);
    $fabric_query_manager = $this->createMock(FabricQueryManager::class);
    $fabric_query_manager->method('createInstance')->with('attachment')->willReturn($attachment_query);

    $container = new Container();
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $container->set('plugin.manager.endpoint_query_manager', $this->createMock(EndpointQueryManager::class));
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    return CompositeMap::create($container, [], 'composite_map', []);
  }

}
