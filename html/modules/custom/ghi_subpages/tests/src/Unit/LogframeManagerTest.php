<?php

namespace Drupal\Tests\ghi_subpages\Unit;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_subpages\LogframeManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests generated logframe column configuration.
 *
 * @coversDefaultClass \Drupal\ghi_subpages\LogframeManager
 * @group ghi_subpages
 */
class LogframeManagerTest extends UnitTestCase {

  /**
   * Tests that generated caseload columns use canonical metric types.
   */
  public function testBuildCaseloadColumnsUsesMetricTypes(): void {
    $prototype = $this->mockAttachmentPrototype(
      ['in_need', 'target', 'cumulative_reach', 'periodical_reach', 'latest_reach'],
      [
        'latest_reach' => 'Latest reached',
        'periodical_reach' => 'Reached',
        'cumulative_reach' => 'Cumulative reached',
      ],
    );

    $columns = $this->invokeBuilder('buildCaseloadColumns', $prototype);

    $this->assertSame('in_need', $columns[1]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('target', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('cumulative_reach', $columns[3]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('cumulative_reach', $columns[4]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('target', $columns[4]['config']['data_point']['data_points'][1]['metric_type']);
    $this->assertStringNotContainsString('"index"', json_encode($columns));
  }

  /**
   * Tests that generated indicator columns use canonical metric types.
   */
  public function testBuildIndicatorColumnsUsesMetricTypes(): void {
    $prototype = $this->mockAttachmentPrototype([
      'target',
      'cumulative_measure',
      'periodical_measure',
    ]);

    $columns = $this->invokeBuilder('buildIndicatorColumns', $prototype);

    $this->assertSame('target', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('periodical_measure', $columns[3]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('periodical_measure', $columns[4]['config']['data_point']);
    $this->assertSame('target', $columns[4]['config']['baseline']);
    $this->assertStringNotContainsString('"index"', json_encode($columns));
  }

  /**
   * Invoke a private column builder on the service.
   */
  private function invokeBuilder(string $method_name, AttachmentPrototype $prototype): array {
    $manager = $this->getMockBuilder(LogframeManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $manager->setStringTranslation($this->getStringTranslationStub());

    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanLanguage')->willReturn('en');

    $method = new \ReflectionMethod(LogframeManager::class, $method_name);
    $method->setAccessible(TRUE);
    return $method->invoke($manager, $prototype, $plan);
  }

  /**
   * Mock an attachment prototype with the requested fields.
   */
  private function mockAttachmentPrototype(array $field_types, array $measurement_fields = []): AttachmentPrototype {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    $prototype->method('getMeasurementFields')->willReturn($measurement_fields);
    $prototype->method('getDefaultFieldLabel')->willReturnCallback(function (string $metric_type): string {
      return $metric_type;
    });
    return $prototype;
  }

}
