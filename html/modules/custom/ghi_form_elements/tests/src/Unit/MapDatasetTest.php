<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\ghi_form_elements\Element\MapDataset;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the map dataset form element.
 *
 * @group ghi_form_elements
 */
class MapDatasetTest extends UnitTestCase {

  /**
   * Tests feedback for a selected metric without location-level data.
   */
  public function testUnavailableMetricFeedback(): void {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('id')->willReturn(51955);
    $attachment->method('getPlanningFields')->willReturn([
      'in_need' => 'People in need',
      'expected_reach' => 'Expected reach',
      'affected' => 'Affected people',
    ]);
    $attachment->method('getMeasurementFields')->willReturn([]);
    $attachment->method('getFieldTypes')->willReturn([
      'in_need',
      'expected_reach',
      'affected',
    ]);
    $attachment->method('getFields')->willReturn([
      'in_need' => 'People in need',
      'expected_reach' => 'Expected reach',
      'affected' => 'Affected people',
    ]);
    $attachment->method('getPlanId')->willReturn(1208);
    $attachment->method('getTitle')->willReturn('BP1');
    $attachment->method('getDescription')->willReturn('Baseline population');
    $attachment->method('isMeasurementField')->willReturn(FALSE);

    $attachment_query = $this->createMock(AttachmentQuery::class);
    $attachment_query->method('getAttachment')->with(51955)->willReturn($attachment);
    $attachment_query->method('getMappableMapMetricAvailability')->with($attachment)->willReturn([
      'base' => ['in_need'],
      'measurements' => [],
    ]);
    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->method('createInstance')->with('attachment')->willReturn($attachment_query);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.fabric_query_manager', $query_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $element = [
      '#array_parents' => ['datasets'],
      '#parents' => ['datasets'],
      '#default_value' => [
        'full_pie' => [
          'attachment' => 51955,
          'metric' => 'in_need',
          'settings' => [],
        ],
        'polygon' => [
          'attachment' => 51955,
          'metric' => MapDataset::NONE,
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
      '#attachment_ids' => [51955],
      '#max_slices' => 2,
      '#dataset_id' => 'test-map',
    ];

    MapDataset::processMapDataset($element, new FormState());

    $this->assertSame('People in need', $element['polygon']['metric']['#options']['in_need']);
    $this->assertSame('Expected reach', $element['polygon']['metric']['#options']['expected_reach']);
    $this->assertSame('Affected people — No location-level data', (string) $element['polygon']['metric']['#options']['affected']);
    $this->assertSame('["in_need","expected_reach","affected"]', $element['polygon']['metric']['#attributes']['data-map-dataset-disabled-options']);
    $this->assertSame('Expected reach — No location-level data', (string) $element['slices'][0]['metric']['#options']['expected_reach']);
    $this->assertSame('People in need', $element['slices'][0]['metric']['#options']['in_need']);
    $this->assertSame('["in_need","affected"]', $element['slices'][0]['metric']['#attributes']['data-map-dataset-disabled-options']);
    $this->assertSame(
      'No location-level data is available for Expected reach. This dataset will not appear on the map.',
      (string) $element['slices'][0]['settings_summary']['availability_warning']['#markup'],
    );
    $this->assertArrayNotHasKey('availability_warning', $element['full_pie']['settings_summary']);
  }

}
