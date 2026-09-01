<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\OrganizationClusterList;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery;
use Drupal\hpc_api\Plugin\FabricQuery\IconQuery;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the organization cluster list configuration item.
 *
 * @group ghi_blocks
 *
 * @coversDefaultClass \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\OrganizationClusterList
 */
class OrganizationClusterListTest extends UnitTestCase {

  /**
   * Tests icon output references the imported SVG file.
   *
   * @covers ::getRenderArray
   */
  public function testIconRenderUsesImportedSvgFile(): void {
    drupal_static_reset();
    $organization = new Organization((object) [
      'Id' => 1,
      'Name' => 'Organization one',
    ]);
    $cluster = new PlanProjectCluster((object) [
      'Id' => 5958,
      'Name' => 'Food Security',
      'icon' => (object) ['Name' => 'clusters_food_security_icon'],
    ]);
    $plan = $this->createMock(Plan::class);
    $project_query = $this->createMock(ProjectQuery::class);
    $project_query->expects($this->once())
      ->method('getProjectClustersByOrganization')
      ->with($plan, NULL)
      ->willReturn([$organization->id() => [$cluster->id() => $cluster]]);
    $icon_query = $this->createMock(IconQuery::class);
    $icon_query->expects($this->once())
      ->method('getMonochromeIconUri')
      ->with('clusters_food_security_icon')
      ->willReturn('public://icons/clusters_food_security_icon.monochrome.svg');

    $item = new OrganizationClusterList([], 'organization_cluster_list', []);
    $item->set('display_icons', TRUE);
    $item->setContextValue('plan_object', $plan);
    $item->setContextValue('base_object', NULL);
    $item->setContextValue('organization', $organization);
    $item->projectQuery = $project_query;
    $item->iconQuery = $icon_query;

    $build = $item->getRenderArray();

    $this->assertSame('Food Security', $build[0]['#tooltip']);
    $this->assertSame('span', $build[0]['#tag_content']['#tag']);
    $this->assertSame(['cluster-icon', 'icon'], $build[0]['#tag_content']['#attributes']['class']);
    $this->assertSame('image', $build[0]['#tag_content']['icon']['#theme']);
    $this->assertSame('public://icons/clusters_food_security_icon.monochrome.svg', $build[0]['#tag_content']['icon']['#uri']);
    $this->assertSame('', $build[0]['#tag_content']['icon']['#alt']);
  }

}
