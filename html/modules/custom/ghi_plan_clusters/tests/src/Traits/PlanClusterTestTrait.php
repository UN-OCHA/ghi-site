<?php

namespace Drupal\Tests\ghi_plan_clusters\Traits;

use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;
use Drupal\ghi_plan_clusters\PlanClusterManager;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Provides methods to create plan clusters in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait PlanClusterTestTrait {

  use SubpageTestTrait;
  use BaseObjectTestTrait;

  /**
   * Create a plan cluster node object.
   *
   * @return \Drupal\ghi_plan_clusters\Entity\PlanCluster
   *   A plan cluster node object.
   */
  public function createPlanCluster(SectionNodeInterface $section = NULL) {
    // Create a plan object.
    if ($section === NULL) {
      $plan = $this->createBaseObject([
        'type' => 'plan',
      ]);
      $plan->save();
    }
    else {
      $plan = $section->getBaseObject();
    }

    // Create a GVE object associated to that plan.
    $governing_entity = $this->createBaseObject([
      'type' => PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'field_plan' => $plan,
    ]);
    $governing_entity->save();

    // Create a section.
    if ($section === NULL) {
      $section = $this->createSection([
        'field_base_object' => $plan,
      ]);
      $section->save();
    }

    // Confirm the cluster subpage exists now and that it is correctly setup.
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertInstanceOf(PlanCluster::class, $plan_cluster);
    $this->assertInstanceOf(SectionNodeInterface::class, $plan_cluster->getParentBaseNode());
    $this->assertInstanceOf(GoverningEntity::class, $plan_cluster->getBaseObject());
    $this->assertNotEmpty($this->planClusterManager->loadGoverningEntityBaseObjectsForSection($section));
    $this->assertNotEmpty($this->planClusterManager->loadNodesForSection($section));

    return $plan_cluster;
  }

  /**
   * Create a plan cluster content type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created node type.
   */
  private function createPlanClusterContentTypes() {
    $this->createBaseObjectType([
      'id' => PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'label' => 'Governing entity',
    ]);

    $this->createEntityReferenceField('base_object', PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY, 'field_plan', 'Plan', 'base_object', 'default', [
      'target_bundles' => ['plan'],
    ]);

    // Create the section bundle.
    $plan_cluster_type = $this->createContentType([
      'type' => PlanClusterInterface::BUNDLE,
      'name' => 'Plan cluster',
    ]);

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = $this->container->get('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('node', PlanClusterInterface::BUNDLE);
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->createEntityReferenceField('node', PlanClusterInterface::BUNDLE, PlanCluster::BASE_OBJECT_FIELD_NAME, 'Governing Entity', 'base_object', 'default', [
      'target_bundles' => [PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY],
    ]);
    $this->createEntityReferenceField('node', PlanClusterInterface::BUNDLE, PlanCluster::SECTION_REFERENCE_FIELD_NAME, 'Section', 'node', 'default', [
      'target_bundles' => [SectionNodeInterface::BUNDLE],
    ]);

    $display_repository->getFormDisplay('node', PlanClusterInterface::BUNDLE)
      ->setComponent(PlanCluster::BASE_OBJECT_FIELD_NAME, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->save();

    $pattern = $this->createPattern('node', '[node:field_entity_reference:entity:url:path]/ge/[node:field_base_object:entity:field_original_id]');
    $this->addBundleCondition($pattern, 'node', PlanClusterInterface::BUNDLE);
    $pattern->save();

    return $plan_cluster_type;
  }

}
