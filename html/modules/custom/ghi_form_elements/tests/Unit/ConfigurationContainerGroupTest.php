<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;

/**
 * @covers Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup
 */
class ConfigurationContainerGroupTest extends UnitTestCase {

  /**
   * Test buildTree.
   *
   * @group ConfigurationContainerGroup
   */
  public function testGetGroups() {

    $items = [
      'item_1' => [
        'item_type' => 'some_type',
      ],
      'item_2' => [
        'item_type' => 'item_group',
      ],
      'item_3' => [
        'item_type' => 'some_type',
      ],
    ];

    /** @var \Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup $trait */
    $trait = $this->getObjectForTrait(ConfigurationContainerGroup::class);
    $this->assertEquals($trait->getGroups($items), ['item_2' => $items['item_2']]);
  }

  /**
   * Data provider for testBuildTree.
   */
  public function buildTreeDataProvider() {
    $items = [
      0 => [
        'id' => 4,
        'item_type' => 'item_group',
        'config' => [
          'label' => 'Population',
          'id' => 'population',
        ],
        'weight' => -10,
        'pid' => NULL,
      ],
      1 => [
        'id' => 5,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'people_in_need',
        ],
        'weight' => -10,
        'pid' => 4,
      ],
      2 => [
        'id' => 6,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'people_target',
        ],
        'weight' => -9,
        'pid' => 4,
      ],
      3 => [
        'id' => 7,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'people_reached_percent',
        ],
        'weight' => -8,
        'pid' => 4,
      ],
      4 => [
        'id' => 0,
        'item_type' => 'item_group',
        'config' => [
          'label' => 'Financials',
          'id' => 'financials',
        ],
        'weight' => -9,
        'pid' => NULL,
      ],
      5 => [
        'id' => 1,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'total_requirements',
        ],
        'weight' => -10,
        'pid' => 0,
      ],
      6 => [
        'id' => 3,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'funding_progress',
        ],
        'weight' => -9,
        'pid' => 0,
      ],
      7 => [
        'id' => 2,
        'item_type' => 'plan_overview_data',
        'config' => [
          'type' => 'total_funding',
        ],
        'weight' => -8,
        'pid' => 0,
      ],
    ];

    $expected_result = [
      0 => [
        'id' => 4,
        'item_type' => 'item_group',
        'config' => [
          'label' => 'Population',
          'id' => 'population',
        ],
        'weight' => -10,
        'pid' => NULL,
        'children' => [
          [
            'id' => 5,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'people_in_need',
            ],
            'weight' => -10,
          ],
          [
            'id' => 6,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'people_target',
            ],
            'weight' => -9,
          ],
          [
            'id' => 7,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'people_reached_percent',
            ],
            'weight' => -8,
          ],
        ],
      ],
      1 => [
        'id' => 0,
        'item_type' => 'item_group',
        'config' => [
          'label' => 'Financials',
          'id' => 'financials',
        ],
        'weight' => -9,
        'pid' => NULL,
        'children' => [
          [
            'id' => 1,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'total_requirements',
            ],
            'weight' => -10,
          ],
          [
            'id' => 3,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'funding_progress',
            ],
            'weight' => -9,
          ],
          [
            'id' => 2,
            'item_type' => 'plan_overview_data',
            'config' => [
              'type' => 'total_funding',
            ],
            'weight' => -8,
          ],
        ],
      ],
    ];

    return [
      [$items, $expected_result],
    ];
  }

  /**
   * Test buildTree.
   *
   * @group ConfigurationContainerGroup
   * @dataProvider buildTreeDataProvider
   */
  public function testBuildTree($items, $result) {
    /** @var \Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup $trait */
    $trait = $this->getObjectForTrait(ConfigurationContainerGroup::class);
    $this->assertEquals($trait->buildTree($items), $result);
  }

}
