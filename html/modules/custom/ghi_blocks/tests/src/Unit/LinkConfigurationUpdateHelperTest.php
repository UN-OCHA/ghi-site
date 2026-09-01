<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\Helpers\LinkConfigurationUpdateHelper;
use Drupal\layout_builder\SectionComponent;

/**
 * @covers Drupal\ghi_blocks\Helpers\LinkConfigurationUpdateHelper
 */
class LinkConfigurationUpdateHelperTest extends UnitTestCase {

  /**
   * Test updatePlanHeadlineFiguresComponent with empty items.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdatePlanHeadlineFiguresComponentEmptyItems() {
    $component = $this->createMockComponent([]);
    $result = LinkConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updatePlanHeadlineFiguresComponent with add_link.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdatePlanHeadlineFiguresComponentWithAddLink() {
    $configuration = [
      'hpc' => [
        'key_figures' => [
          'items' => [
            [
              'item_type' => 'item_group',
              'config' => [
                'label' => 'Test Group',
                'link' => [
                  'add_link' => TRUE,
                  'link' => [
                    'label' => 'Test Link',
                    'url' => '/test',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    $component = $this->createMockComponent($configuration);
    $result = LinkConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component);

    $this->assertTrue($result);
  }

  /**
   * Test updateLinksComponent with empty links.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdateLinksComponentEmptyLinks() {
    $component = $this->createMockComponent([]);
    $result = LinkConfigurationUpdateHelper::updateLinksComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updateLinksComponent with link item.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdateLinksComponentWithLinkItem() {
    $configuration = [
      'hpc' => [
        'links' => [
          'links' => [
            [
              'item_type' => 'link',
              'config' => [
                'link' => [
                  'url' => 'http://example.com',
                  'date' => '2023-01-01',
                  'description' => 'Test',
                  'description_toggle' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    $component = $this->createMockComponent($configuration);
    $result = LinkConfigurationUpdateHelper::updateLinksComponent($component);

    $this->assertTrue($result);
  }

  /**
   * Test updatePlanEntityTypesComponent with empty display.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdatePlanEntityTypesComponentEmptyDisplay() {
    $component = $this->createMockComponent([]);
    $result = LinkConfigurationUpdateHelper::updatePlanEntityTypesComponent($component);
    $this->assertFalse($result);
  }

  /**
   * Test updatePlanEntityTypesComponent with link.
   *
   * @group LinkConfigurationUpdateHelper
   */
  public function testUpdatePlanEntityTypesComponentWithLink() {
    $configuration = [
      'hpc' => [
        'display' => [
          'link' => [
            'add_link' => TRUE,
            'label' => 'View Details',
            'url' => '/details',
          ],
        ],
      ],
    ];
    $component = $this->createMockComponentWithDisplay($configuration);
    $result = LinkConfigurationUpdateHelper::updatePlanEntityTypesComponent($component);

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

  /**
   * Create a mock SectionComponent for display tests.
   */
  private function createMockComponentWithDisplay(array $configuration) {
    $component = $this->createMock(SectionComponent::class);
    $component->method('get')
      ->willReturnCallback(function ($key) use ($configuration) {
        if ($key === 'configuration') {
          return $configuration;
        }
        if ($key === 'display') {
          return 'default';
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
