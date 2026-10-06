<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Map\MapPayload;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCompositeMap;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\Tests\UnitTestCase;

/**
 * Tests composite maps rendering their unsaved state in both preview contexts.
 *
 * @group ghi_blocks
 */
class PlanCompositeMapPreviewTest extends UnitTestCase {

  /**
   * Tests that both previews build data and tabs from the current block.
   *
   * @dataProvider previewContextProvider
   */
  public function testUnsavedPreviewBuildsMapInline(bool $configuration_preview): void {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('hasDisaggregatedData')->willReturn(TRUE);
    $attachment->method('canBeMapped')->willReturn(TRUE);
    $query = $this->createMock(AttachmentQuery::class);
    $query->method('getAttachmentsById')->with([10])->willReturn([10 => $attachment]);
    $block = $this->getMockBuilder(PlanCompositeMap::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getBlockConfig',
        'getQueryHandler',
        'getCurrentBaseObject',
        'getMapDataUrl',
        'isConfigurationPreview',
        'isLayoutBuilder',
        'isLayoutBuilderFormSubmission',
        'buildLazyMapPayload',
        'preparePreviewMap',
      ])
      ->getMock();
    $block->method('getBlockConfig')->willReturn([
      'attachments' => [
        'entity_attachments' => [
          'entities' => ['entity_ids' => [1 => 1]],
          'attachments' => ['attachment_id' => [10 => 10]],
        ],
      ],
      'maps' => ['maps' => [['id' => 0]]],
    ]);
    $block->method('getQueryHandler')->with('attachment')->willReturn($query);
    $plan = $this->createMock(Plan::class);
    $plan->method('getCacheTags')->willReturn([]);
    $plan->method('getCacheContexts')->willReturn([]);
    $plan->method('getCacheMaxAge')->willReturn(0);
    $block->method('getCurrentBaseObject')->willReturn($plan);
    $block->method('getMapDataUrl')->willReturn(NULL);
    $block->method('isConfigurationPreview')->willReturn($configuration_preview);
    $block->method('isLayoutBuilder')->willReturn(!$configuration_preview);
    $block->method('isLayoutBuilderFormSubmission')->willReturn(FALSE);
    $map = ['json' => [['label' => 'Unsaved dataset']], 'settings_key' => 'plan_composite_map'];
    $tabs = ['#markup' => 'Unsaved tab'];
    $block->expects($this->once())->method('buildLazyMapPayload')->willReturnCallback(function (string $map_id) use ($map, $tabs): MapPayload {
      return MapPayload::forMap($map + ['id' => $map_id], [], [], MapPayload::cacheabilityFromTags([]), [
        '.pane-' . $map_id . ' .map-tabs--inner' => $tabs,
      ]);
    });
    $block->expects($this->once())->method('preparePreviewMap')->willReturnArgument(0);

    $build = $block->buildContent()[0];

    $this->assertSame($tabs, $build['#map_tabs']);
    $settings = $build['#attached']['drupalSettings']['plan_composite_map'][$build['#chart_id']];
    $this->assertSame($map['json'], $settings['json']);
    $this->assertArrayNotHasKey('data_url', $settings);
  }

  /**
   * Provides interactive configuration and non-interactive canvas previews.
   */
  public static function previewContextProvider(): array {
    return [
      'configuration preview' => [TRUE],
      'Layout Builder canvas' => [FALSE],
    ];
  }

}
