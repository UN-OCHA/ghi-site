<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\Core\Url;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\node\NodeInterface;

/**
 * Helper class for handling plan structure logic.
 */
class PlanStructureHelper {

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
      $structure = [
        'plan_entities' => [],
        'governing_entities' => [],
      ];

      $prototype = unserialize($plan_node->field_plan_structure_rpm->value);
      if (!$prototype) {
        return;
      }
      ArrayHelper::sortObjectsByProperty($prototype, 'orderNumber');

      // List of possible PLE types above the first GVE.
      $main_ref_codes = ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES;

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
              // Ignore this, it's probably an "xor" thing that we don't want
              // to handle at the moment.
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
      $plan_structures[$plan_node->id()] = $structure;
    }

    return $plan_structures[$plan_node->id()];
  }

}
