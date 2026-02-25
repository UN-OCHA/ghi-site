<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Helpers\ApiEntityHelper;

/**
 * Helper class for handling plan structure logic.
 *
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
class PlanStructureHelper {

  use PlanQueryTrait;

  /**
   * Retrieve the plan entity structure based on the given plan id.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of API entity objects.
   */
  public static function getPlanEntityStructure(int $plan_id) {

    $plan_entity_query = self::getEntityQuery();
    $plan_entities = $plan_entity_query->getEntitiesForPlan($plan_id, NULL, 'plan');
    $governing_entities = $plan_entity_query->getEntitiesForPlan($plan_id, NULL, 'governing');

    $remove_ids = [];
    $ple_structure = [];
    if (!empty($plan_entities)) {
      foreach ($plan_entities as $entity_id => $plan_entity) {
        /** @var \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $plan_entity */
        // First see if this PLE is actually a child of a GVE. If so, put it
        // there.
        $governing_entity_id = $plan_entity->getGoverningEntityParentId();
        if ($governing_entity_id && !empty($governing_entities[$governing_entity_id])) {
          $governing_entities[$governing_entity_id]->addChild($plan_entity);
          $remove_ids[] = $entity_id;
        }
        elseif (!empty($plan_entity->support[0]->planEntityIds)) {
          // If not, put the PLEs according to their structure.
          foreach ($plan_entity->support[0]->planEntityIds as $ple_id) {
            if (!array_key_exists($ple_id, $plan_entities)) {
              $plan_entities[$ple_id] = PlanEntityHelper::getPlanEntity($ple_id);
            }
            if ($plan_entities[$ple_id] instanceof PlanEntity) {
              $ple_structure[$plan_entity->id()] = $plan_entity;
            }
            else {
              if (empty($ple_structure[$ple_id])) {
                $ple_structure[$ple_id] = $plan_entities[$ple_id];
              }
              $ple_structure[$ple_id]->addChild($plan_entity);
            }
          }
        }
        else {
          $ple_structure[$plan_entity->id()] = $plan_entity;
        }
      }
    }

    if (!empty($governing_entities)) {
      foreach ($governing_entities as $entity_id => $governing_entity) {
        $ple_structure[$governing_entity->id()] = $governing_entity;
      }
    }

    if (!empty($plan_entities)) {
      if (!empty($remove_ids)) {
        foreach ($remove_ids as $remove_id) {
          unset($plan_entities[$remove_id]);
        }
      }
      foreach ($plan_entities as $entity_id => $plan_entity) {
        $ple_structure[$plan_entity->id()] = $plan_entity;
      }
    }
    return $ple_structure;
  }

  /**
   * Get the entity structure of the given plan.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan entity.
   *
   * @return array
   *   An array describing the plan structure.
   */
  public static function getPlanStructure(Plan $plan): ?array {

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery $entity_prototype_query */
    $entity_prototype_query = self::getEntityPrototypeQuery();
    // Get the prototype data for analysis.
    $prototype = $entity_prototype_query?->getPlanPrototype($plan->getSourceId());
    if (!$prototype) {
      return NULL;
    }

    // List of possible PLE types above the first GVE.
    $main_ref_codes = ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES;

    $structure = [
      'plan_entities' => [],
      'governing_entities' => [],
    ];

    foreach ($prototype->getEntityPrototypes() as $entity_prototype) {
      if (!$entity_prototype->isPlanEntity() || !in_array($entity_prototype->getRefCode(), $main_ref_codes)) {
        continue;
      }
      // There is always a main plan entity.
      $main_level_ple = empty($entity_prototype->getSupportedPrototypeIds());
      $structure['plan_entities'][$entity_prototype->id()] = (object) [
        'label' => $entity_prototype->getNamePlural(),
        'label_singular' => $entity_prototype->getNameSingular(),
        'entity_type' => $entity_prototype->getType(),
        'entity_prototype_id' => $entity_prototype->id(),
        'entity_prototype_child_ids' => $entity_prototype->getChildrenPrototypeIds(),
        'drupal_entity_type' => 'plan_entity',
        'subpage' => $main_level_ple ? 'pe' : NULL,
      ];
    }

    // And then there are usually one or more governing entities.
    $ge_index = 0;
    foreach ($prototype->getEntityPrototypes() as $entity_prototype) {
      if ($entity_prototype->isGoverningEntity()) {
        $ge_index++;
        $subpage = 'ge' . (($ge_index == 1) ? '' : ('-' . $ge_index));
        $structure['governing_entities'][$entity_prototype->id()] = (object) [
          'subpage' => $subpage,
          'label' => $entity_prototype->getNamePlural(),
          'label_singular' => $entity_prototype->getNameSingular(),
          'entity_type' => $entity_prototype->getType(),
          'entity_prototype_id' => $entity_prototype->id(),
          'entity_prototype_child_ids' => $entity_prototype->getChildrenPrototypeIds(),
          'drupal_entity_type' => 'governing_entity',
        ];
      }
      if ($entity_prototype->isPlanEntity() && !empty($entity_prototype->getSupportedPrototypeIds())) {
        // Some plan entities can support other plan entities.
        foreach ($entity_prototype->getSupportedPrototypeIds() as $supported_prototype_id) {
          $parent_entity_id = $supported_prototype_id;
          if (empty($structure['plan_entities'][$parent_entity_id])) {
            // Not sure what this means, skip it for the moment.
            // @todo Research why this happens.
            continue;
          }
          if (empty($structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids)) {
            $structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids = [];
          }
          $structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids[] = $entity_prototype->id();
        }
      }
    }
    return $structure;
  }

  /**
   * Build a plan structure for use in GHI.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   *
   * @return array
   *   An array describing the plan structure.
   */
  public static function getRpmPlanStructure(Plan $plan) {
    $plan_structures = &drupal_static(__FUNCTION__);
    if (empty($plan_structures[$plan->id()])) {
      $plan_structures[$plan->id()] = self::getPlanStructure($plan);
    }

    return $plan_structures[$plan->id()];
  }

}
