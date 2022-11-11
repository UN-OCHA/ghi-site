<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\ghi_plans\ApiObjects\PlanPrototype;
use Drupal\hpc_api\Helpers\ApiEntityHelper;

/**
 * Helper class for handling plan structure logic.
 *
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
class PlanStructureHelper {

  /**
   * Retrieve the plan entity structure based on the given plan data.
   *
   * @param object $plan_data
   *   The full plan data object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of API entity objects.
   */
  public static function getPlanEntityStructure($plan_data) {

    $plan_entities = PlanEntityHelper::getPlanEntityObjects($plan_data);
    $governing_entities = PlanEntityHelper::getGoverningEntityObjects($plan_data);

    $remove_ids = [];
    $ple_structure = [];
    if (!empty($plan_entities)) {
      foreach ($plan_entities as $entity_id => $plan_entity) {
        // First see if this PLE is actually a child of a GVE. If so, put it
        // there.
        if (!empty($plan_entity->parent_id) && !empty($governing_entities[$plan_entity->parent_id])) {
          $governing_entities[$plan_entity->parent_id]->addChild($plan_entity);
          $remove_ids[] = $entity_id;
        }
        elseif ($plan_entity->root_parent_id) {
          // This PLE is a child of another main level PLE so we put it in
          // there.
          $parent_id = $plan_entity->root_parent_id;
          if (!array_key_exists($parent_id, $plan_entities)) {
            $plan_entities[$parent_id] = PlanEntityHelper::getPlanEntity($parent_id);
          }
          $plan_entities[$parent_id]->addChild($plan_entity);
          $remove_ids[] = $entity_id;
        }
        elseif (!empty($plan_entity->support[0]->planEntityIds)) {
          // If not, put the PLEs according to their structure.
          foreach ($plan_entity->support[0]->planEntityIds as $ple_id) {
            if (!array_key_exists($ple_id, $plan_entities)) {
              $plan_entities[$ple_id] = PlanEntityHelper::getPlanEntity($ple_id);
            }
            if ($plan_entities[$ple_id]->entity_type == 'PE') {
              $ple_structure[$plan_entity->id] = $plan_entity;
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
          $ple_structure[$plan_entity->id] = $plan_entity;
        }
      }
    }

    if (!empty($governing_entities)) {
      foreach ($governing_entities as $entity_id => $governing_entity) {
        $ple_structure[$governing_entity->id] = $governing_entity;
      }
    }

    if (!empty($plan_entities)) {
      if (!empty($remove_ids)) {
        foreach ($remove_ids as $remove_id) {
          unset($plan_entities[$remove_id]);
        }
      }
      foreach ($plan_entities as $entity_id => $plan_entity) {
        $ple_structure[$plan_entity->id] = $plan_entity;
      }
    }
    return $ple_structure;
  }

  /**
   * Extract the plan structure form the prototype API data.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanPrototype $prototype
   *   An array of prototypes retrieved from the API.
   * @param \Drupal\Core\Entity\ContentEntityInterface $plan_object
   *   The plan object that the prototype belongs too.
   *
   * @return array
   *   An array describing the plan structure.
   */
  public static function getPlanStructureFromPrototype(PlanPrototype $prototype, ContentEntityInterface $plan_object) {

    // List of possible PLE types above the first GVE.
    $main_ref_codes = ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES;

    $structure = [
      'plan_entities' => [],
      'governing_entities' => [],
    ];

    foreach ($prototype->items as $entity_prototype) {
      if ($entity_prototype->type != 'PE' || !in_array($entity_prototype->ref_code, $main_ref_codes)) {
        continue;
      }
      // There is always a main plan entity.
      $main_level_ple = empty($entity_prototype->can_support);
      $structure['plan_entities'][$entity_prototype->id] = (object) [
        'label' => $entity_prototype->name_plural,
        'label_singular' => $entity_prototype->name_singular,
        'entity_type' => $entity_prototype->type,
        'entity_prototype_id' => $entity_prototype->id,
        'drupal_entity_type' => 'plan_entity',
        'subpage' => $main_level_ple ? 'pe' : NULL,
      ];
    }

    // And then there are usually one or more governing entities.
    $ge_index = 0;
    foreach ($prototype->items as $entity_prototype) {
      if ($entity_prototype->type == 'GVE') {
        $ge_index++;
        $subpage = 'ge' . (($ge_index == 1) ? '' : ('-' . $ge_index));
        $structure['governing_entities'][$entity_prototype->id] = (object) [
          'subpage' => $subpage,
          'label' => $entity_prototype->name_plural,
          'label_singular' => $entity_prototype->name_singular,
          'entity_type' => $entity_prototype->type,
          'entity_prototype_id' => $entity_prototype->id,
          'entity_prototype_child_ids' => !empty($entity_prototype->children) ? array_map(function ($item) {
            return $item->id;
          }, $entity_prototype->children) : [],
          'drupal_entity_type' => 'governing_entity',
        ];
      }
      if ($entity_prototype->type == 'PE' && !empty($entity_prototype->can_support)) {
        // Some plan entities can support other plan entities.
        foreach ($entity_prototype->can_support as $supported_prototype) {
          if (!is_object($supported_prototype)) {
            // Ignore this, it's probably an "xor" thing that we don't want to
            // handle at the moment.
            continue;
          }
          $parent_entity_id = $supported_prototype->id;
          if (empty($structure['plan_entities'][$parent_entity_id])) {
            // Not sure what this means, skip it for the moment.
            // @todo Research why this happens.
            continue;
          }
          if (empty($structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids)) {
            $structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids = [];
          }
          $structure['plan_entities'][$parent_entity_id]->entity_prototype_child_ids[] = $entity_prototype->id;
        }
      }
    }
    return $structure;
  }

  /**
   * Build a plan structure for use in GHI.
   *
   * The plan structure can be customized in RPM and is retrieved via the plan
   * prototype endpoint. We need to parse the data and abstract it for easier
   * use.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $plan_object
   *   The plan object.
   */
  public static function getRpmPlanStructure(ContentEntityInterface $plan_object) {
    $plan_structures = &drupal_static(__FUNCTION__);
    if (empty($plan_structures[$plan_object->id()])) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanPrototypeQuery $plan_prototype_query */
      $plan_prototype_query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('plan_prototype_query');
      $prototype = $plan_prototype_query->getPrototype($plan_object->field_original_id->value);
      if (!$prototype) {
        return;
      }
      $plan_structures[$plan_object->id()] = self::getPlanStructureFromPrototype($prototype, $plan_object);
    }

    return $plan_structures[$plan_object->id()];
  }

}
