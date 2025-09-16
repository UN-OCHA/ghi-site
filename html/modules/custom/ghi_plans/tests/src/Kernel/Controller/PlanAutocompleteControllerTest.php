<?php

namespace Drupal\Tests\ghi_plans\Kernel\Controller;

use Drupal\ghi_plans\Controller\PlanAutocompleteController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for the PlanAutocompleteController.
 *
 * @coversDefaultClass \Drupal\ghi_plans\Controller\PlanAutocompleteController
 * @group ghi_plans
 */
class PlanAutocompleteControllerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'field',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'publishcontent',
    'hpc_api',
    'ghi_base_objects',
    'hpc_common',
    'ghi_plans',
  ];

  /**
   * The controller under test.
   *
   * @var \Drupal\ghi_plans\Controller\PlanAutocompleteController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installSchema('system', ['sequences']);

    // Create plan content type.
    NodeType::create([
      'type' => 'plan',
      'name' => 'Plan',
    ])->save();

    // Add field_plan_year and field_original_id fields to plan content type.
    $this->createField('field_plan_year', 'plan', 'integer');
    $this->createField('field_original_id', 'plan', 'integer');

    $this->controller = PlanAutocompleteController::create($this->container);
  }

  /**
   * Helper method to create a field.
   */
  protected function createField($field_name, $bundle, $field_type) {
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
      ]);
    $field_storage->save();

    $field = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => ucfirst(str_replace('_', ' ', $field_name)),
      ]);
    $field->save();
  }

  /**
   * Tests planAutocomplete with empty query parameter.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteWithEmptyQuery(): void {
    $request = Request::create('/test', 'GET', ['q' => '']);
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([], $content);
  }

  /**
   * Tests planAutocomplete with no query parameter.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteWithNoQuery(): void {
    $request = Request::create('/test', 'GET');
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([], $content);
  }

  /**
   * Tests planAutocomplete with valid query but no matching nodes.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteWithNoMatchingNodes(): void {
    $request = Request::create('/test', 'GET', ['q' => 'nonexistent']);
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([], $content);
  }

  /**
   * Tests planAutocomplete with matching nodes.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteWithMatchingNodes(): void {
    // Create test plan nodes with different years.
    $plan1 = Node::create([
      'type' => 'plan',
      'title' => 'Test Plan 2023',
      'field_plan_year' => 2023,
      'field_original_id' => 123,
    ]);
    $plan1->save();

    $plan2 = Node::create([
      'type' => 'plan',
      'title' => 'Test Plan 2022',
      'field_plan_year' => 2022,
      'field_original_id' => 456,
    ]);
    $plan2->save();

    $plan3 = Node::create([
      'type' => 'plan',
      'title' => 'Another Test Plan 2024',
      'field_plan_year' => 2024,
      'field_original_id' => 789,
    ]);
    $plan3->save();

    $request = Request::create('/test', 'GET', ['q' => 'Test Plan']);
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);

    // Should return 3 matching plans.
    $this->assertCount(3, $content);

    // Check that plans are sorted by year (descending).
    $this->assertEquals('Another Test Plan 2024(789)', $content[0]['label']);
    $this->assertEquals('Test Plan 2023(123)', $content[1]['label']);
    $this->assertEquals('Test Plan 2022(456)', $content[2]['label']);

    // Check structure of results.
    foreach ($content as $item) {
      $this->assertArrayHasKey('value', $item);
      $this->assertArrayHasKey('label', $item);
      $this->assertIsString($item['value']);
      $this->assertIsString($item['label']);
      // Label should contain plan ID in parentheses.
      $this->assertStringContainsString('(', $item['label']);
      $this->assertStringContainsString(')', $item['label']);
    }
  }

  /**
   * Tests planAutocomplete year sorting with equal years.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteYearSortingWithEqualYears(): void {
    // Create test plan nodes with same year.
    $plan1 = Node::create([
      'type' => 'plan',
      'title' => 'Plan A 2023',
      'field_plan_year' => 2023,
      'field_original_id' => 111,
    ]);
    $plan1->save();

    $plan2 = Node::create([
      'type' => 'plan',
      'title' => 'Plan B 2023',
      'field_plan_year' => 2023,
      'field_original_id' => 222,
    ]);
    $plan2->save();

    $request = Request::create('/test', 'GET', ['q' => 'Plan']);
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);

    // Should return 2 matching plans.
    $this->assertCount(2, $content);

    // Both plans should be present regardless of order since years are equal.
    $labels = array_column($content, 'label');
    $this->assertContains('Plan A 2023(111)', $labels);
    $this->assertContains('Plan B 2023(222)', $labels);
  }

  /**
   * Tests planAutocomplete value and label format.
   *
   * @covers ::planAutocomplete
   */
  public function testPlanAutocompleteValueAndLabelFormat(): void {
    $plan = Node::create([
      'type' => 'plan',
      'title' => 'Sample Plan',
      'field_plan_year' => 2023,
      'field_original_id' => 999,
    ]);
    $plan->save();

    $request = Request::create('/test', 'GET', ['q' => 'Sample']);
    $response = $this->controller->planAutocomplete($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), TRUE);

    $this->assertCount(1, $content);
    $result = $content[0];

    // Value should be the title.
    $this->assertEquals('Sample Plan', $result['value']);

    // Label should be title + (original_id).
    $this->assertEquals('Sample Plan(999)', $result['label']);
  }

}
