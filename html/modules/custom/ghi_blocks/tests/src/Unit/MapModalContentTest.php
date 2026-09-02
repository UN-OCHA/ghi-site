<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\Tests\UnitTestCase;

/**
 * Tests map modal content helpers.
 *
 * @group ghi_blocks
 */
class MapModalContentTest extends UnitTestCase {

  /**
   * Tests extraction from a tabbed map data structure with variants.
   */
  public function testExtractFromTabbedMap(): void {
    $map = [
      'json' => [
        'people-targeted-0' => [
          'locations' => [
            ['object_id' => 1],
          ],
          'modal_contents' => [
            '1' => ['html' => '<p>Base modal</p>'],
          ],
          'variants' => [
            'period-1' => [
              'locations' => [
                ['object_id' => 1],
              ],
              'modal_contents' => [
                '1' => ['html' => '<p>Variant modal</p>'],
              ],
            ],
          ],
        ],
      ],
    ];

    $entries = MapModalContent::extractFromMap($map);

    $this->assertCount(2, $entries);
    $this->assertSame('people-targeted-0', $entries[0]['data_index']);
    $this->assertSame(MapModalContent::DEFAULT_VARIANT_ID, $entries[0]['variant_id']);
    $this->assertSame(['1' => ['html' => '<p>Base modal</p>']], $entries[0]['modal_contents']);
    $this->assertSame('period-1', $entries[1]['variant_id']);
    $this->assertSame(['1' => ['html' => '<p>Variant modal</p>']], $entries[1]['modal_contents']);
    $this->assertArrayNotHasKey('modal_contents', $map['json']['people-targeted-0']);
    $this->assertArrayNotHasKey('modal_contents', $map['json']['people-targeted-0']['variants']['period-1']);
  }

  /**
   * Tests extraction from a root map data structure.
   */
  public function testExtractFromRootMap(): void {
    $map = [
      'json' => [
        'locations' => [
          ['object_id' => 10],
        ],
        'modal_contents' => [
          '10' => ['content' => '<p>Presence modal</p>'],
        ],
      ],
    ];

    $entries = MapModalContent::extractFromMap($map);

    $this->assertCount(1, $entries);
    $this->assertSame(MapModalContent::DEFAULT_DATA_INDEX, $entries[0]['data_index']);
    $this->assertSame(MapModalContent::DEFAULT_VARIANT_ID, $entries[0]['variant_id']);
    $this->assertSame(['10' => ['content' => '<p>Presence modal</p>']], $entries[0]['modal_contents']);
    $this->assertArrayNotHasKey('modal_contents', $map['json']);
    $this->assertSame([['object_id' => 10]], $map['json']['locations']);
  }

  /**
   * Tests extraction from compact object-filter variants.
   */
  public function testExtractFromObjectFilterVariants(): void {
    $map = [
      'json' => [
        'locations' => [
          ['object_id' => 10],
        ],
        'object_filter_variants' => [
          '100' => [
            'location_ids' => [10],
            'modal_contents' => [
              '10' => ['content' => '<p>Filtered modal</p>'],
            ],
          ],
        ],
      ],
    ];

    $entries = MapModalContent::extractFromMap($map);

    $this->assertCount(1, $entries);
    $this->assertSame(MapModalContent::DEFAULT_DATA_INDEX, $entries[0]['data_index']);
    $this->assertSame('100', $entries[0]['variant_id']);
    $this->assertSame(['10' => ['content' => '<p>Filtered modal</p>']], $entries[0]['modal_contents']);
    $this->assertArrayNotHasKey('modal_contents', $map['json']['object_filter_variants']['100']);
    $this->assertSame([10], $map['json']['object_filter_variants']['100']['location_ids']);
  }

}
