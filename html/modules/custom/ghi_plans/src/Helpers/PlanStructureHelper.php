<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

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
          foreach ($plan_entity->getParentIds() as $ple_id) {
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

}
