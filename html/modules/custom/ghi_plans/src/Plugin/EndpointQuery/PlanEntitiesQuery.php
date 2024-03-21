<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
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
 *     "api_key" = "plan/{plan_id}",
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
  use PlanVersionArgument;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $this->endpointQuery->setPlaceholders($placeholders);
    if ($plan_id = $this->getPlaceholder('plan_id')) {
      $query_args['version'] = $this->getPlanVersionArgumentForPlanId($plan_id);
    }
    return parent::getData($placeholders, $query_args);
  }

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
    $cache_key = $this->getCacheKey(array_filter(['id' => $context_object ? $context_object->id() : NULL] + $filter + $this->getPlaceholders()));
    $attachments = $this->getCache($cache_key);
    if ($attachments) {
      return $attachments;
    }

    $data = $this->getData();

    if (empty($data)) {
      return [];
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
      $plan_id = $data->id;
      $attachments = array_map(function ($attachment) use ($plan_id) {
        $attachment->objectId = $plan_id;
        $attachment->objectType = 'plan';
        return $attachment;
      }, $attachments);
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
        $entity_id = $entity->id;
        $entity_attachments = array_map(function ($attachment) use ($entity_id, $property) {
          $attachment->objectId = $entity_id;
          $attachment->objectType = $property;
          return $attachment;
        }, $entity->attachments);
        $attachments = array_merge($attachments, $entity_attachments);
      }
      if ($context_object instanceof GoverningEntity && $plan_entities = $this->getPlanEntities($context_object)) {
        // This is a governing entity, so we must also look for child elements.
        foreach ($plan_entities as $plan_entity) {
          $entity_attachments = array_map(function ($attachment) use ($plan_entity) {
            $attachment->objectId = $plan_entity->id();
            $attachment->objectType = $plan_entity->getEntityType();
            return $attachment;
          }, $plan_entity->getRawData()->attachments ?? []);
          $attachments = array_merge($attachments, $entity_attachments);
        }
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
          $entity_id = $entity->id;
          $entity_attachments = array_map(function ($attachment) use ($entity_id, $property) {
            $attachment->objectId = $entity_id;
            $attachment->objectType = $property;
            return $attachment;
          }, $entity->attachments);
          $attachments = array_merge($attachments, $entity_attachments);
        }
      }
    }

    if (!empty($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
    }
    $this->setCache($cache_key, $attachments);
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
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

    // Map some filters from what the external caller should use, to what we
    // use internally on the raw data before creating actual Attachment objects
    // using AttachmentHelper::processAttachments.
    if (!empty($filter['entity_type'])) {
      $filter['objectType'] = $filter['entity_type'];
      unset($filter['entity_type']);
    }
    if (!empty($filter['entity_id'])) {
      $filter['objectId'] = $filter['entity_id'];
      unset($filter['entity_id']);
    }

    if (!empty($filter['prototype_id'])) {
      $filter['attachmentPrototype.id'] = $filter['prototype_id'];
      unset($filter['prototype_id']);
    }

    return AttachmentHelper::processAttachments($this->getAttachments($context_object, $filter));
  }

  /**
   * Get contact attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects for the given context.
   */
  public function getContactAttachments(ContentEntityInterface $context_object = NULL) {
    $attachments = [];
    foreach ($this->getAttachments($context_object, ['type' => 'contact']) as $attachment) {
      $attachments[$attachment->id] = AttachmentHelper::processAttachment($attachment);
    }
    return $attachments;
  }

  /**
   * Get webcontent file attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment[]
   *   An array of attachment objects for the given context.
   */
  public function getWebContentFileAttachments(ContentEntityInterface $context_object = NULL) {
    $attachments = [];
    foreach ($this->getAttachments($context_object, ['type' => 'fileWebContent']) as $attachment) {
      if (empty($attachment->attachmentVersion->value->file->url)) {
        continue;
      }
      $attachments[$attachment->id] = AttachmentHelper::processAttachment($attachment);
    }
    return $attachments;
  }

  /**
   * Get webcontent text attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment[]
   *   An array of attachment objects for the given context.
   */
  public function getWebContentTextAttachments(ContentEntityInterface $context_object) {
    $attachments = [];
    foreach ($this->getAttachments($context_object, ['type' => 'textWebContent']) as $attachment) {
      if (empty($attachment->attachmentVersion->value->content ?? '')) {
        continue;
      }
      $attachments[$attachment->id] = AttachmentHelper::processAttachment($attachment);
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
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]|null
   *   An array of plan entity objects for the given context or NULL.
   */
  public function getPlanEntities(ContentEntityInterface $context_object = NULL, $entity_type = NULL, array $filters = NULL) {
    $cache_key = $this->getCacheKey(array_filter([
      'id' => $context_object ? $context_object->id() : NULL,
      'entity_type' => $entity_type,
    ] + ($filters ?? [])));

    $plan_entities = $this->getCache($cache_key);
    if ($plan_entities) {
      return $plan_entities;
    }
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    $matching_entities = ApiEntityHelper::getMatchingPlanEntities($data, $context_object?->bundle() != 'plan' ? $context_object : NULL, $entity_type);
    if (empty($matching_entities)) {
      return NULL;
    }

    $matching_entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $matching_entities);
    $matching_entities = array_combine($matching_entity_ids, $matching_entities);

    $plan_entities = array_map(function ($entity) {
      return PlanEntityHelper::getObject($entity);
    }, $matching_entities);

    if (is_array($filters) && !empty($filters)) {
      $plan_entities = ArrayHelper::filterArray($plan_entities, $filters);
    }
    $entity_ids = array_map(function (EntityObjectInterface $entity) {
      return $entity->id();
    }, $plan_entities);
    $plan_entities = array_combine($entity_ids, $plan_entities);
    $this->setCache($cache_key, $plan_entities);
    return $plan_entities;
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
    $cache_key = $this->getCacheKey(['plan_entity_id' => $plan_entity_id] + $this->getPlaceholders());
    $cluster_ids = $this->getCache($cache_key);
    if ($cluster_ids) {
      return $cluster_ids;
    }
    // Get the plan structure.
    $ple_structure = PlanStructureHelper::getPlanEntityStructure($this->getData());
    $cluster_ids = [];
    foreach ($ple_structure as $plan_item) {
      if (in_array($plan_item->id, $cluster_ids)) {
        continue;
      }
      if (empty($plan_item->getChildren())) {
        continue;
      }
      foreach ($plan_item->getChildren() as $child) {
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
    $this->setCache($cache_key, $cluster_ids);
    return $cluster_ids;
  }

  /**
   * Get options for the entity type dropdown.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   An array of plan entities to extract the ref code options.
   *
   * @return array
   *   An array with valid options for the current context.
   */
  public function getEntityRefCodeOptions(array $entities) {
    $options = [];
    if (empty($entities)) {
      return $options;
    }
    $weight = [];
    foreach ($entities as $entity) {
      $ref_code = $entity->getEntityTypeRefCode();
      if (empty($options[$ref_code])) {
        $name = $entity->getTypeName();
        $options[$ref_code] = $name;
        $weight[$ref_code] = $entity->order_number;
      }
    }
    uksort($options, function ($ref_code_a, $ref_code_b) use ($weight) {
      return $weight[$ref_code_a] - $weight[$ref_code_b];
    });
    return $options;
  }

}
