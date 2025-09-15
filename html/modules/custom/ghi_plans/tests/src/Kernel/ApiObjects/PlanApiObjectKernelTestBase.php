<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\Tests\ghi_base_objects\Kernel\ApiObjects\BaseObjectKernelTestBase;

/**
 * Base class for ghi_plans ApiObject kernel tests.
 *
 * @group ghi_plans
 */
abstract class PlanApiObjectKernelTestBase extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'migrate',
    'ghi_base_objects',
    'ghi_plans',
    'hpc_api',
    'hpc_common',
  ];

  /**
   * Create mock raw data for API objects.
   *
   * @param array $data_overrides
   *   Optional data to override defaults.
   *
   * @return object
   *   Mock raw data object.
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $default_data = [
      'id' => rand(1, 1000),
      'name' => $this->randomString(),
    ];

    $merged_overrides = array_merge($default_data, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

}
