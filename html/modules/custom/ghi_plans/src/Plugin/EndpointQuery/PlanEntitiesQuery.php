<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan entities.
 *
 * @EndpointQuery(
 *   id = "plan_entities_query",
 *   label = @Translation("Plan entities query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}",
 *     "authenticated" = "plan/{plan_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "content" = "entities",
 *       "addPercentageOfTotalTarget" = "true",
 *       "version" = "current",
 *       "disaggregation" = "false",
 *     }
 *   }
 * )
 */
class PlanEntitiesQuery extends EndpointQueryBase {

  use AttachmentFilterTrait;

  /**
   * Get all attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param array $filter
   *   Optional array for filtering the attachments. This supports specifically
   *   to filter for "entity_type", the allowed values for that are: "plan"
   *   (looking only at plan attachments), "plan_entity" and "governing_entity"
   *   (to look only at attachments on the specific entity type).
   *   Note: Filtering by entity type in this way has a lower priority for the
   *   selection of entities than the passed in context object. So if the
   *   context object is of type "plan_entity" and a $filter['entity_type'] is
   *   set, then it will be ignored.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  private function getAttachments(ContentEntityInterface $context_object = NULL, array $filter = []) {
    $data = $this->getData();

    if (empty($data)) {
      return NULL;
    }
    $attachments = [];

    // Supported types of context objects.
    $supported_contexts = [
      'plan_entity' => 'planEntities',
      'governing_entity' => 'governingEntities',
    ];

    // Note that this will be ignored if a context object of a supported type
    // has been given.
    $restrict_entity_type = !empty($filter['entity_type']) ? $filter['entity_type'] : NULL;
    unset($filter['entity_type']);

    if (!$restrict_entity_type || $restrict_entity_type == 'plan') {
      // No restriction or plan level.
      $attachments = $data->attachments;
    }

    if ($context_object && array_key_exists($context_object->bundle(), $supported_contexts)) {
      // A supported context object has been given. Go over all the entities of
      // the given type and find the one object that corresponds to the given
      // context object. Then use it's attachments.
      $context_original_id = $context_object->field_original_id->value;
      $property = $supported_contexts[$context_object->bundle()];
      foreach ($data->$property as $entity) {
        if ($entity->id != $context_original_id) {
          continue;
        }
        if (!isset($entity->attachments)) {
          continue;
        }
        $attachments = array_merge($attachments, $entity->attachments);
      }
    }
    else {
      // No context object has been given. So we either collect all attachments
      // for all plan/governing entities, or only the ones requested by
      // $restrict_entity_type.
      foreach ($supported_contexts as $entity_type => $property) {
        if ($restrict_entity_type && $entity_type !== $restrict_entity_type) {
          continue;
        }
        foreach ($data->$property as $entity) {
          if (!isset($entity->attachments)) {
            continue;
          }
          $attachments = array_merge($attachments, $entity->attachments);
        }
      }
    }

    if (!empty($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
    }
    return $attachments;
  }

  /**
   * Get data attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param array $filter
   *   Optional array for filtering the attachments.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getDataAttachments(ContentEntityInterface $context_object = NULL, array $filter = NULL) {
    $allowed_types = [
      'caseload',
      'indicator',
    ];

    if (empty($filter['type'])) {
      $filter['type'] = $allowed_types;
    }
    else {
      $filter['type'] = array_filter((array) $filter['type'], function ($item) use ($allowed_types) {
        return in_array($item, $allowed_types);
      });
    }

    return AttachmentHelper::processAttachments($this->getAttachments($context_object, $filter));
  }

  /**
   * Get webcontent file attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getWebContentFileAttachments(ContentEntityInterface $context_object = NULL) {
    $attachments = [];
    foreach ($this->getAttachments($context_object, ['type' => 'fileWebContent']) as $attachment) {
      if (empty($attachment->attachmentVersion->value->file->url)) {
        continue;
      }
      $attachments[] = AttachmentHelper::processAttachment($attachment);
    }
    return $attachments;
  }

  /**
   * Get webcontent text attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getWebContentTextAttachments(ContentEntityInterface $context_object) {
    $attachments = [];
    foreach ($this->getAttachments($context_object, ['type' => 'textWebContent']) as $attachment) {
      if (empty($attachment->attachmentVersion->value->content ?? '')) {
        continue;
      }
      $attachments[] = AttachmentHelper::processAttachment($attachment);
    }
    return $attachments;
  }

  /**
   * Get available plan entities for the given context.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param string $entity_type
   *   The entity type to restrict the context.
   * @param array $filters
   *   The optional aray with filter key value pairs.
   *
   * @return array
   *   An array of plan entity objects for the given context.
   */
  public function getPlanEntities(ContentEntityInterface $context_object, $entity_type = NULL, array $filters = NULL) {
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    $matching_entities = ApiEntityHelper::getMatchingPlanEntities($this->getData(), $context_object->bundle() != 'plan' ? $context_object : NULL, $entity_type);
    if (empty($matching_entities)) {
      return NULL;
    }
    $plan_entities = array_map(function ($entity) {
      $entity_version = ApiEntityHelper::getEntityVersion($entity);
      return (object) [
        'id' => $entity->id,
        'name' => $this->getEntityName($entity),
        'plural_name' => $entity->entityPrototype->value->name->en->plural,
        'order_number' => $entity->entityPrototype->orderNumber,
        'ref_code' => $entity->entityPrototype->refCode,
        'prototype_id' => $entity->entityPrototype->id,
        'custom_id' => $entity_version->customReference,
        'custom_id_prefixed_refcode' => $entity->entityPrototype->refCode . $entity_version->customReference,
        'composed_reference' => $entity->composedReference,
        'description' => property_exists($entity_version->value, 'description') ? $entity_version->value->description : NULL,
        'icon' => !empty($entity_version->value->icon) ? $entity_version->value->icon : NULL,
        'tags' => property_exists($entity_version, 'tags') ? $entity_version->tags : [],
      ];
    }, $matching_entities);

    if (is_array($filters) && !empty($filters)) {
      $plan_entities = ArrayHelper::filterArray($plan_entities, $filters);
    }
    return $plan_entities;
  }

  /**
   * Get the name of an entity.
   *
   * @param object $entity
   *   The entity object.
   *
   * @return string
   *   The name of the given entity.
   */
  private function getEntityName($entity) {
    $entity_version = ApiEntityHelper::getEntityVersion($entity);
    if (property_exists($entity_version, 'name')) {
      // Governing entity.
      return $entity_version->name;
    }
    // Plan entity.
    return $entity->entityPrototype->value->name->en->singular . ' ' . $entity_version->customReference;
  }

  /**
   * Extract cluster IDs for the given context from the given plan.
   *
   * @param int $plan_entity_id
   *   A plan entity ID (original id from HPC).
   *
   * @return array
   *   An array of cluster IDs.
   */
  public function getGoverningEntityIdsForPlanEntityId($plan_entity_id) {
    // Get the plan structure.
    $ple_structure = PlanStructureHelper::getPlanEntityStructure($this->getData());
    $cluster_ids = [];
    foreach ($ple_structure as $plan_item) {
      if (in_array($plan_item->id, $cluster_ids)) {
        continue;
      }
      if (empty($plan_item->children)) {
        continue;
      }
      foreach ($plan_item->children as $child) {
        if (in_array($plan_item->id, $cluster_ids)) {
          continue;
        }
        if (empty($child->support[0]->planEntityIds)) {
          continue;
        }
        if (in_array($plan_entity_id, $child->support[0]->planEntityIds)) {
          $cluster_ids[] = $plan_item->id;
        }
      }
    }
    return $cluster_ids;
  }

}
