<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\Core\Url;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\NodeHelper;
use Drupal\node\NodeInterface;

/**
 * Helper class for handling plan structure logic.
 */
class PlanStructureHelper {

  /**
   * Retrieve the plan entity structure based on the given plan data.
   */
  public static function getPlanEntityStructure($plan_data) {

    $plan_entities = $plan_data->planEntities;
    $governing_entities = $plan_data->governingEntities;

    $simple_plan_entities = [];
    $simple_governing_entities = [];

    if (!empty($governing_entities)) {
      foreach (array_keys($governing_entities) as $key) {
        $entity = $governing_entities[$key];
        $entity_version = ApiEntityHelper::getEntityVersion($entity);
        $node = NodeHelper::getNodeFromOriginalId($entity->id, 'governing_entity');
        $simple_governing_entities[$entity->id] = (object) [
          'id' => $entity->id,
          'name' => $entity->composedReference . ': ' . $entity_version->name,
          'group_name' => $entity->composedReference . ': ' . $entity_version->name,
          'display_name' => $entity->composedReference . ': ' . $entity_version->name,
          'entity_name' => $entity_version->name,
          'entity_type' => $entity->entityPrototype->type,
          'entity_prototype_id' => $entity->entityPrototype->id,
          'order_number' => $entity_version->value->orderNumber,
          'custom_reference' => $entity_version->customReference,
          'composed_reference' => $entity->composedReference,
          'path' => $node ? 'node/' . $node->id() : NULL,
          'sort_key' => property_exists($entity_version->value, 'orderNumber') ? $entity_version->value->orderNumber : ($entity->entityPrototype->orderNumber . $entity->customReference),
          'published' => $node ? $node->isPublished() : NULL,
        ];
      }
    }
    ArrayHelper::sortObjectsByStringProperty($simple_governing_entities, 'sort_key', EndpointQuery::SORT_ASC);

    $main_level_ple_hierarchy = [];
    if (!empty($plan_entities)) {

      foreach (array_keys($plan_entities) as $key) {
        $entity = $plan_entities[$key];
        $entity_version = ApiEntityHelper::getEntityVersion($entity);

        if (in_array($entity->entityPrototype->refCode, ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES) && !empty($entity_version->value->support)) {
          $parent_id = reset(reset($entity_version->value->support)->planEntityIds);
          $main_level_ple_hierarchy[$entity->id] = $parent_id;
        }

        $node = NodeHelper::getNodeFromOriginalId($entity->id, 'plan_entity');

        $simple_plan_entities[$entity->id] = (object) [
          'id' => $entity->id,
          'name' => $entity->entityPrototype->value->name->en->singular,
          'name_plural' => $entity->entityPrototype->value->name->en->plural,
          'group_name' => $entity->entityPrototype->value->name->en->plural,
          'display_name' => $entity->entityPrototype->value->name->en->singular . ' ' . $entity_version->customReference,
          // Need to cast to array until HPC-6440 is fixed.
          'support' => !empty($entity_version->value->support) ? (array) $entity_version->value->support : NULL,
          'ref_code' => $entity->entityPrototype->refCode,
          'entity_type' => $entity->entityPrototype->type,
          'entity_prototype_id' => $entity->entityPrototype->id,
          'order_number' => $entity->entityPrototype->orderNumber,
          'parent_id' => !empty($entity->parentId) ? $entity->parentId : NULL,
          'custom_reference' => $entity_version->customReference,
          'composed_reference' => $entity->composedReference,
          'path' => $node ? 'node/' . $node->id() : NULL,
          'sort_key' => $entity->entityPrototype->orderNumber . $entity_version->customReference,
          'published' => $node ? $node->isPublished() : NULL,
        ];
      }
    }
    ArrayHelper::sortObjectsByStringProperty($simple_plan_entities, 'sort_key', EndpointQuery::SORT_ASC);

    $ple_structure = [];
    if (!empty($simple_plan_entities)) {
      foreach ($simple_plan_entities as $entity_id => $simple_plan_entity) {
        // First see if this PLE is actually a child of a GVE. If so, put it
        // there.
        if (!empty($simple_plan_entity->parent_id) && !empty($simple_governing_entities[$simple_plan_entity->parent_id])) {
          if (empty($simple_governing_entities[$simple_plan_entity->parent_id]->children)) {
            $simple_governing_entities[$simple_plan_entity->parent_id]->children = [];
          }
          $simple_governing_entities[$simple_plan_entity->parent_id]->children[] = $simple_plan_entity;
          $simple_plan_entities[$entity_id]->remove = TRUE;
        }
        elseif (in_array($simple_plan_entity->ref_code, ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES) && !empty($main_level_ple_hierarchy[$entity_id])) {
          // This PLE is a child of another main level PLE so we put it in
          // there.
          $parent_id = $main_level_ple_hierarchy[$entity_id];
          if (empty($simple_plan_entities[$parent_id]->children)) {
            $simple_plan_entities[$parent_id]->children = [];
          }
          $simple_plan_entities[$parent_id]->children[$entity_id] = $simple_plan_entity;
          $simple_plan_entities[$entity_id]->remove = TRUE;
        }
        elseif (!empty($simple_plan_entity->support[0]->planEntityIds)) {
          // If not, put the PLEs according to their structure.
          foreach ($simple_plan_entity->support[0]->planEntityIds as $ple_id) {
            if ($simple_plan_entities[$ple_id]->entity_type == 'PE') {
              $ple_structure[$simple_plan_entity->id] = $simple_plan_entity;
            }
            else {
              if (empty($ple_structure[$ple_id])) {
                $ple_structure[$ple_id] = $simple_plan_entities[$ple_id];
                $ple_structure[$ple_id]->children = [];
              }
              $ple_structure[$ple_id]->children[$simple_plan_entity->id] = $simple_plan_entity;
            }
          }
        }
        else {
          $ple_structure[$simple_plan_entity->id] = $simple_plan_entity;
        }
      }
    }

    if (!empty($simple_governing_entities)) {
      foreach ($simple_governing_entities as $entity_id => $simple_governing_entity) {
        if (!empty($simple_governing_entity->children)) {
          ArrayHelper::sortObjectsByStringProperty($simple_governing_entity->children, 'sort_key', EndpointQuery::SORT_ASC);
        }
        $ple_structure[$simple_governing_entity->id] = $simple_governing_entity;
      }
    }

    if (!empty($simple_plan_entities)) {
      foreach ($simple_plan_entities as $entity_id => $simple_plan_entity) {
        if (!empty($simple_plan_entity->remove)) {
          unset($simple_plan_entities[$entity_id]);
          continue;
        }
        if (!empty($simple_plan_entity->children)) {
          ArrayHelper::sortObjectsByStringProperty($simple_plan_entity->children, 'sort_key', EndpointQuery::SORT_ASC);
        }
        $ple_structure[$simple_plan_entity->id] = $simple_plan_entity;
      }

    }

    return $ple_structure;
  }

  /**
   * Extract the plan structure form the prototype API data.
   *
   * @param array $prototype
   *   An array of prototypes retrieved from the API.
   * @param \Drupal\node\NodeInterface $plan_node
   *   The node object that the prototype belongs too.
   *
   * @return array
   *   An array describing the plan structure.
   */
  public static function getPlanStructureFromPrototype(array $prototype, NodeInterface $plan_node) {
    ArrayHelper::sortObjectsByProperty($prototype, 'orderNumber');

    // List of possible PLE types above the first GVE.
    $main_ref_codes = ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES;

    $structure = [
      'plan_entities' => [],
      'governing_entities' => [],
    ];

    foreach ($prototype as $entity_prototype) {
      if ($entity_prototype->type != 'PE' || !in_array($entity_prototype->refCode, $main_ref_codes)) {
        continue;
      }
      // There is always a main plan entity.
      $main_level_ple = empty($entity_prototype->value->canSupport);
      $structure['plan_entities'][$entity_prototype->id] = (object) [
        'label' => $entity_prototype->value->name->en->plural,
        'label_singular' => $entity_prototype->value->name->en->singular,
        'entity_type' => $entity_prototype->type,
        'entity_prototype_id' => $entity_prototype->id,
        'drupal_entity_type' => 'plan_entity',
        'subpage' => $main_level_ple ? 'pe' : NULL,
        'url' => $main_level_ple ? Url::fromUserInput('/node/' . $plan_node->id())->toString() . '/pe' : NULL,
      ];
    }

    // And then there are usually one or more governing entities.
    $ge_index = 0;
    foreach ($prototype as $entity_prototype) {
      if ($entity_prototype->type == 'GVE') {
        $ge_index++;
        $subpage = 'ge' . (($ge_index == 1) ? '' : ('-' . $ge_index));
        $structure['governing_entities'][$entity_prototype->id] = (object) [
          'subpage' => $subpage,
          'url' => Url::fromUserInput('/node/' . $plan_node->id())->toString() . '/' . $subpage,
          'label' => $entity_prototype->value->name->en->plural,
          'label_singular' => $entity_prototype->value->name->en->singular,
          'entity_type' => $entity_prototype->type,
          'entity_prototype_id' => $entity_prototype->id,
          'entity_prototype_child_ids' => !empty($entity_prototype->value->possibleChildren) ? array_map(function ($item) {
            return $item->id;
          }, $entity_prototype->value->possibleChildren) : [],
          'drupal_entity_type' => 'governing_entity',
        ];
      }
      if ($entity_prototype->type == 'PE' && !empty($entity_prototype->value->canSupport)) {
        // Some plan entities can support other plan entities.
        foreach ($entity_prototype->value->canSupport as $supported_prototype) {
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
   * Build a plan structure for use in HPC Viewer.
   *
   * The plan structure can be customized in RPM and is retrieved via the plan
   * prototype endpoint. We need to parse the data and abstract it for easier
   * use.
   *
   * @param \Drupal\node\NodeInterface $plan_node
   *   The plan node object.
   */
  public static function getRpmPlanStructure(NodeInterface $plan_node) {
    $plan_structures = &drupal_static(__FUNCTION__);
    if (empty($plan_structures[$plan_node->id()])) {
      if (empty($plan_node->field_plan_structure_rpm->value)) {
        return;
      }
      $prototype = unserialize($plan_node->field_plan_structure_rpm->value);
      if (!$prototype) {
        return;
      }
      $plan_structures[$plan_node->id()] = self::getPlanStructureFromPrototype($prototype, $plan_node);
    }

    return $plan_structures[$plan_node->id()];
  }

}
