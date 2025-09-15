<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Project;

/**
 * Tests the Project API object.
 *
 * @group ghi_plans
 */
class ProjectTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $project_defaults = [
      'versionCode' => 'V1',
      'governingEntities' => [],
      'currentPublishedVersionId' => rand(1, 100),
      'currentRequestedFunds' => 1000000,
      'locationIds' => (object) ['ids' => [1, 2, 3]],
      'targets' => [
        (object) ['total' => 100],
        (object) ['total' => 200],
        (object) ['total' => 300],
      ],
    ];

    $merged_overrides = array_merge($project_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test Project constructor and mapping.
   */
  public function testProjectConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Project',
      'code' => 'PROJ123',
    ]);

    $project = new Project($raw_data);

    $this->assertApiObjectBasics($project, 'project');

    // Test Project-specific properties.
    $this->assertEquals(123, $project->id());
    $this->assertEquals('Test Project', $project->getName());

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('project', $project->getBundle());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(Project::class);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $project = new Project($raw_data);

    $cache_tags = $project->getCacheTags();
    $this->assertIsArray($cache_tags);
  }

}
