<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\Helpers\FundingDataConfigurationUpdateHelper;
use Drupal\layout_builder\SectionComponent;

/**
 * @covers Drupal\ghi_blocks\Helpers\FundingDataConfigurationUpdateHelper
 */
class FundingDataConfigurationUpdateHelperTest extends UnitTestCase {

  /**
   * Test updateGlobalKeyFiguresComponent with empty items.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdateGlobalKeyFiguresComponentEmptyItems() {
    $component = $this->createMockComponent([]);
    $result = FundingDataConfigurationUpdateHelper::updateGlobalKeyFiguresComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updateGlobalKeyFiguresComponent with non-matching item type.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdateGlobalKeyFiguresComponentNonMatchingType() {
    $configuration = [
      'hpc' => [
        'key_figures' => [
          'items' => [
            [
              'item_type' => 'other_type',
              'config' => [
                'type' => 'funding_progress',
                'label' => 'Coverage',
              ],
            ],
          ],
        ],
      ],
    ];
    $component = $this->createMockComponent($configuration);
    $result = FundingDataConfigurationUpdateHelper::updateGlobalKeyFiguresComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updatePlanHeadlineFiguresComponent with empty items.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdatePlanHeadlineFiguresComponentEmptyItems() {
    $component = $this->createMockComponent([]);
    $result = FundingDataConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updatePlanHeadlineFiguresComponent with matching funding_data.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdatePlanHeadlineFiguresComponentMatchingData() {
    $configuration = [
      'hpc' => [
        'key_figures' => [
          'items' => [
            [
              'item_type' => 'funding_data',
              'config' => [
                'data_type' => 'funding_coverage',
                'label' => 'Coverage',
              ],
            ],
          ],
        ],
      ],
    ];
    $component = $this->createMockComponent($configuration);
    $result = FundingDataConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component);
    $this->assertTrue($result);
  }

  /**
   * Test updateStandardTableComponent with empty columns.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdateStandardTableComponentEmptyColumns() {
    $component = $this->createMockComponent([]);
    $result = FundingDataConfigurationUpdateHelper::updateStandardTableComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updateStandardTableComponent with matching funding_data.
   *
   * @group FundingDataConfigurationUpdateHelper
   */
  public function testUpdateStandardTableComponentMatchingData() {
    $configuration = [
      'hpc' => [
        'table' => [
          'columns' => [
            [
              'item_type' => 'funding_data',
              'config' => [
                'data_type' => 'funding_coverage',
                'label' => 'Coverage',
              ],
            ],
          ],
        ],
      ],
    ];
    $component = $this->createMockComponent($configuration);
    $result = FundingDataConfigurationUpdateHelper::updateStandardTableComponent($component);
    $this->assertTrue($result);
  }

  /**
   * Create a mock SectionComponent.
   */
  private function createMockComponent(array $configuration) {
    $component = $this->createMock(SectionComponent::class);
    $component->method('get')
      ->willReturnCallback(function ($key) use ($configuration) {
        if ($key === 'configuration') {
          return $configuration;
        }
        return NULL;
      });

    $component->method('setConfiguration')
      ->willReturnCallback(function ($config) {
        return NULL;
      });

    return $component;
  }

}
