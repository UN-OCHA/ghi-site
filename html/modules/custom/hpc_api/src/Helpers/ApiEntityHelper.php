<?php

namespace Drupal\hpc_api\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Helper class to handle entity objects from the HPC API.
 */
class ApiEntityHelper {

  /**
   * A list of hardcoded root level plan entity ref codes.
   */
  const MAIN_LEVEL_PLE_REF_CODES = ['CQ', 'SO', 'SP'];

  /**
   * Supported context entity types for plans.
   *
   * A list of node bundles that are supported for further context handling of
   * plan entities.
   */
  const SUPPORTED_CONTEXT_ENTITY_TYPES = [
    'governing_entity',
    'plan_entity',
  ];

  /**
   * Retrieve the version property of an entity.
   *
   * @param object $entity
   *   The entity object.
   *
   * @return object
   *   The entity version property, should be an object.
   */
  public static function getEntityVersion($entity) {
    $version_properties = [
      'planVersion',
      'planEntityVersion',
      'governingEntityVersion',
    ];
    foreach ($version_properties as $version_property) {
      if (property_exists($entity, $version_property)) {
        return $entity->{$version_property};
      }
    }
    return NULL;
  }

  /**
   * Extract matching plan entities from the given plan data object.
   *
   * @param object $data
   *   A plan data object as retrieved from the API.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_entity
   *   An optional entity object that defines the current context. Should be
   *   NULL when on a plan overview page, but one of plan_entity or
   *   governing_entity on subpages.
   * @param string $entity_type
   *   The optional type of API entity we are looking for, either plan or
   *   governing.
   * @param array $filters
   *   An optional set of filters for further limitation of the result set.
   *
   * @return array
   *   An array of entity objects.
   */
  public static function getMatchingPlanEntities($data, ?ContentEntityInterface $context_entity = NULL, $entity_type = NULL, ?array $filters = NULL) {

    if (empty($data->planEntities) && empty($data->governingEntities)) {
      return FALSE;
    }

    $entities = [];
    if ($entity_type === NULL) {
      $entities = array_merge($data->planEntities, $data->governingEntities);
    }
    else {
      $entity_property = $entity_type == 'plan' ? 'planEntities' : 'governingEntities';
      $entities = $data->$entity_property;
    }

    // Holds matching entities based on the requested data and the given plan
    // context.
    $matching_entities = [];

    if (empty($context_entity) || !in_array($context_entity->bundle(), self::SUPPORTED_CONTEXT_ENTITY_TYPES)) {
      // Easy, no additional plan context, just get all of them.
      $matching_entities = $entities;
    }
    elseif ($context_entity->bundle() == 'governing_entity') {
      // Context is a governing entity, e.g. a cluster. Lets get the parent to
      // drill down into the hierarchy.
      $parent_id = $context_entity->field_original_id->value;
      foreach ($entities as $entity) {
        if ($parent_id !== NULL && (empty($entity->parentId) || $entity->parentId != $parent_id)) {
          continue;
        }
        $matching_entities[] = $entity;
      }
    }
    elseif ($context_entity->bundle() == 'plan_entity') {
      // Context is a plan entity, e.g. a strategic objective.
      $entity_prototype_id = $context_entity->field_prototype_id->value;
      $original_id = $context_entity->field_original_id->value;

      // First extract matching high level entities.
      foreach ($entities as $entity) {
        $entity_version = self::getEntityVersion($entity);

        // Check if the entity prototype is supported.
        if ($entity_prototype_id !== NULL && !empty($entity->entityPrototype->value->canSupport)) {
          $can_support = NULL;
          if (is_array($entity->entityPrototype->value->canSupport)) {
            $can_support = $entity->entityPrototype->value->canSupport;
          }
          elseif (is_object($entity->entityPrototype->value->canSupport)) {
            $can_support = $entity->entityPrototype->value->canSupport->xor;
          }
          if ($can_support) {
            $supported_entity_prototype_ids = array_map(function ($item) {
              return $item->id;
            }, $can_support);
            if (!in_array($entity_prototype_id, $supported_entity_prototype_ids)) {
              continue;
            }
          }
        }
        if (!empty($entity_version->value->support)) {
          $supported_plan_entity_ids = self::getSupportPlanIds($entities, $entity, TRUE);
          // Then check if the entity itself is supported.
          if (!in_array($original_id, $supported_plan_entity_ids)) {
            continue;
          }
        }
        if (!empty($original_id) && $entity->entityPrototype->id == $entity_prototype_id && $entity->entityPrototype->type == 'PE' && $entity->id != $original_id) {
          continue;
        }
        $matching_entities[] = $entity;
      }

      // Next check for child ids.
      $child_ids = self::getPlanEntitiesChildIds($data, $original_id);
      if (!empty($child_ids)) {
        foreach ($entities as $entity) {
          if (!in_array($entity->id, $child_ids) || in_array($entity, $matching_entities)) {
            continue;
          }
          $matching_entities[] = $entity;
        }
      }
    }

    // Also add the context entity itself to the list of matching entities.
    if (!empty($context_entity) && (empty($entity_type) || $context_entity->bundle() == $entity_type . '_entity')) {
      $original_id = $context_entity->field_original_id->value;
      $plan_context_entities = self::getPlanEntitiesById($data, [$original_id]);
      $plan_context_entity = !empty($plan_context_entities) ? reset($plan_context_entities) : NULL;
      if ($plan_context_entity && !in_array($plan_context_entity, $matching_entities)) {
        $matching_entities[] = $plan_context_entity;
      }
    }

    // Filter according to request.
    if ($filters !== NULL && is_array($filters)) {
      $matching_entities = ArrayHelper::filterArray($matching_entities, $filters);
    }

    return $matching_entities;
  }

  /**
   * Retrieve entities attached to a plan by their IDs.
   *
   * @param object $plan_data
   *   The plan data object as retrieved from the API.
   * @param array $ids
   *   The ids of the entities that should be retrieved.
   *
   * @return array
   *   An array of entity object, matching the given ids.
   */
  public static function getPlanEntitiesById($plan_data, array $ids) {
    $entities = [];
    foreach (['planEntities', 'governingEntities'] as $entity_type) {
      if (empty($plan_data->$entity_type) || !is_array($plan_data->$entity_type)) {
        continue;
      }
      foreach ($plan_data->$entity_type as $entity) {
        if (in_array($entity->id, $ids)) {
          $entities[$entity->id] = $entity;
        }
      }
    }
    return $entities;
  }

  /**
   * Get the child ids as they can be extracted from the plan structure.
   *
   * @param object $plan_data
   *   The plan data object as retrieved from the API.
   * @param int $original_id
   *   The original id of the parent.
   *
   * @return array
   *   An array of child ids.
   */
  public static function getPlanEntitiesChildIds($plan_data, $original_id) {
    $child_ids = [];
    if (empty($plan_data->planEntities)) {
      return $child_ids;
    }

    // Get a shortcut to the plan entities.
    $plan_entities = $plan_data->planEntities;

    // First create the PLE relation array. The keys are the PLE ids, the values
    // are the parents.
    $ple_relation = [];
    foreach (array_keys($plan_entities) as $key) {
      $entity = $plan_entities[$key];
      $entity_version = self::getEntityVersion($entity);

      if (in_array($entity->entityPrototype->refCode, self::MAIN_LEVEL_PLE_REF_CODES) && !empty($entity_version->value->support)) {
        $parent_id = reset(reset($entity_version->value->support)->planEntityIds);
        $ple_relation[$entity->id] = $parent_id;
      }
    }

    // Then transform that into a simple array containing all the childs of
    // original_id as its values.
    $child_ids = [$original_id];
    while ($new_childs = array_intersect($ple_relation, $child_ids)) {
      $child_ids = array_merge($child_ids, array_keys($new_childs));
      $ple_relation = array_diff_key($ple_relation, $new_childs);
    }

    return $child_ids;
  }

  /**
   * Get supported plan entities.
   *
   * Supports chained relation swith recursion.
   *
   * @param array $entities
   *   Should be an array of all plan entities in the current set.
   * @param object $entity
   *   The entity for which supported PLEs are to be retrieved.
   * @param bool $chained
   *   Switch to indicate if recursion should be used.
   *
   * @return array
   *   An array of plan ids which are supported by the given $entity.
   */
  public static function getSupportPlanIds(array $entities, $entity, $chained = FALSE) {
    $entity_version = ApiEntityHelper::getEntityVersion($entity);
    if (empty($entity_version->value->support)) {
      return [];
    }

    $supported_plan_entity_ids = [];
    // Need to cast to array until HPC-6440 is fixed.
    $support_items = (array) $entity_version->value->support;
    foreach ($support_items as $support_item) {
      if (empty($support_item->planEntityIds)) {
        continue;
      }
      $supported_plan_entity_ids = array_merge($supported_plan_entity_ids, $support_item->planEntityIds);
      if ($chained && !empty($support_item->planEntityIds)) {
        foreach ($support_item->planEntityIds as $parent_entity_id) {
          $parent_entities = array_filter($entities, function ($item) use ($parent_entity_id) {
            return $item->id == $parent_entity_id;
          });
          if (empty($parent_entities)) {
            continue;
          }
          $parent_entity = reset($parent_entities);
          $supported_plan_entity_ids = array_merge($supported_plan_entity_ids, self::getSupportPlanIds($entities, $parent_entity, $chained));
        }
      }
    }
    return $supported_plan_entity_ids;
  }

  /**
   * Get the public name of an entity prototype.
   *
   * @param object $entity
   *   An entity object.
   * @param bool $plural
   *   Flag for either singgular or plural label output.
   *
   * @return string
   *   The label of the entity prototype.
   */
  public static function getEntityPrototypeName($entity, $plural = TRUE) {
    $prototype = self::getEntityPrototype($entity);
    $property = $plural ? 'plural' : 'singular';
    return $prototype->value->name->en->$property;
  }

  /**
   * Get the entity prototype from an entity.
   *
   * @param object $entity
   *   An entity object.
   *
   * @return object
   *   An entity prototype object.
   */
  public static function getEntityPrototype($entity) {
    return $entity->entityPrototype;
  }

  /**
   * Extract plan data entities (plan or governing) by type.
   *
   * @param object $data
   *   The full plan data object as retrieved from the API.
   * @param string $entity_type
   *   Either "plan" or "governing".
   * @param array $limit
   *   An optional array of filter criteria to apply to the list of entities.
   *   The keys of the array have to match one of the keys used to map the
   *   data in this function.
   *
   * @return array
   *   An array of entities of the given type. Processed for smaller footprint.
   */
  public static function getProcessedPlanEntitesByType($data, $entity_type, ?array $limit = NULL) {
    $property = $entity_type . 'Entities';
    if (empty($data->$property)) {
      return [];
    }
    // Retrieve the entities of the specified type and map the data it something
    // more simple.
    $entities = [];
    foreach ($data->$property as $entity) {
      $entity_version = self::getEntityVersion($entity);
      $supported_plan_entity_ids = self::getSupportPlanIds($entities, $entity, FALSE);
      $entities[$entity->id] = [
        'entity_type' => $entity_type,
        'id' => $entity->id,
        'plan_id' => $entity->planId,
        'name' => !empty($entity_version->name) ? $entity_version->name : $entity->entityPrototype->value->name->en->singular . ' ' . $entity_version->customReference,
        'entity_prototype_id' => $entity->entityPrototype->id,
        'entity_prototype_name' => $entity->entityPrototype->value->name->en->plural,
        'type' => $entity_type . '_entity',
        'custom_reference' => $entity_version->customReference,
        'description' => !empty($entity_version->value->description) ? $entity_version->value->description : '',
        'icon' => !empty($entity_version->value->icon) ? $entity_version->value->icon : NULL,
        'parents' => !empty($entity->parents) ? array_map(function ($item) {
          return $item->parentId;
        }, $entity->parents) : [],
        'weight' => property_exists($entity_version->value, 'orderNumber') ? $entity_version->value->orderNumber : $entity->entityPrototype->orderNumber,
        'parent_id' => !empty($entity->parentId) ? $entity->parentId : NULL,
        'supported_plan_entities' => $supported_plan_entity_ids,
      ];
    }
    // Filter if necessary.
    if ($limit !== NULL && is_array($limit)) {
      $entities = array_filter($entities, function ($entity) use ($limit) {
        $valid = TRUE;
        foreach ($limit as $key => $value) {
          if (is_array($entity[$key])) {
            $valid = $valid && in_array($value, $entity[$key]);
          }
          else {
            $valid = $valid && $entity[$key] == $value;
          }
        }
        return $valid;
      });
    }

    uasort($entities, function ($a, $b) {
      return $a['weight'] - $b['weight'];
    });

    return $entities;
  }

}
