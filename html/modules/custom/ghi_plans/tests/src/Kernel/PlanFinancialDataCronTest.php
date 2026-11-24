<?php

namespace Drupal\Tests\ghi_plans\Kernel;

use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\ghi_base_objects\Kernel\BaseObjectKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests incremental plan financial data updates during cron.
 */
#[Group('ghi_plans')]
class PlanFinancialDataCronTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'ghi_plans',
  ];

  /**
   * Tests cursor rotation and saving only changed plans.
   */
  public function testFinancialDataCronCursor(): void {
    $plan_type = $this->createBaseObjectType([
      'id' => 'plan',
      'field_funding_total' => ['type' => 'decimal', 'label' => 'Total funding'],
      'field_funding_overall' => ['type' => 'decimal', 'label' => 'Overall funding'],
      'field_requirements' => ['type' => 'decimal', 'label' => 'Requirements'],
      'field_requirements_original' => ['type' => 'decimal', 'label' => 'Original requirements'],
    ]);
    $plans = [];
    foreach ([101, 102, 103] as $source_id) {
      $plan = $this->createBaseObject([
        'type' => $plan_type->id(),
        'field_original_id' => $source_id,
        'field_funding_total' => 10,
        'field_funding_overall' => 20,
        'field_requirements' => 30,
        'field_requirements_original' => 40,
      ]);
      $this->assertInstanceOf(Plan::class, $plan);
      $plans[$source_id] = $plan;
    }

    $financial_data = array_fill_keys(array_keys($plans), [
      'total_funding' => 10,
      'overall_funding' => 20,
      'current_requirements' => 30,
      'original_requirements' => 40,
    ]);
    $financial_data[102]['current_requirements'] = 31.004;
    $financial_data[103]['total_funding'] = 11;
    $plan_query = $this->createMock(PlanQuery::class);
    $plan_query->method('getFinancialDataForPlans')->willReturnCallback(fn(array $plan_ids): array => array_intersect_key($financial_data, array_flip($plan_ids)));
    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->method('hasDefinition')->with('plan')->willReturn(TRUE);
    $query_manager->method('createInstance')->with('plan')->willReturn($plan_query);
    $this->container->set('plugin.manager.fabric_query_manager', $query_manager);
    \Drupal::setContainer($this->container);
    drupal_static_reset();
    /** @var \Drupal\ghi_plans\PlanFinancialDataManager $financial_data_manager */
    $financial_data_manager = $this->container->get('ghi_plans.plan_financial_data_manager');

    \Drupal::state()->set('ghi_base_objects_test.base_object_update_count', 0);
    $financial_data_manager->updateNextBatch(2);
    $this->assertSame((int) $plans[102]->id(), \Drupal::state()->get('ghi_plans.financial_data_cron_cursor'));
    $this->assertSame(1, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $storage = $this->container->get('entity_type.manager')->getStorage('base_object');
    $this->assertSame(31.0, $storage->loadUnchanged($plans[102]->id())->getRequirements());

    $financial_data_manager->updateNextBatch(2);
    $this->assertSame((int) $plans[103]->id(), \Drupal::state()->get('ghi_plans.financial_data_cron_cursor'));
    $this->assertSame(2, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $this->assertSame(11.0, $storage->loadUnchanged($plans[103]->id())->getTotalFunding());

    // The next run wraps around without resaving the now unchanged plans.
    $financial_data_manager->updateNextBatch(2);
    $this->assertSame((int) $plans[102]->id(), \Drupal::state()->get('ghi_plans.financial_data_cron_cursor'));
    $this->assertSame(2, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
  }

}
