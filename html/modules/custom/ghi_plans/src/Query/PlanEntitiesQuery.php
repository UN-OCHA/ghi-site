<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Helpers\ArrayHelper;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Query class for fetching plan data with a focus on plan entities.
 */
class PlanEntitiesQuery extends EndpointQuery {

  use AttachmentFilterTrait;

  /**
   * Constructs a new PlanEntitiesQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/plan/{plan_id}';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'plan/{plan_id}';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
    $this->endpointArgs = [
      'content' => 'entities',
      'addPercentageOfTotalTarget' => TRUE,
      'version' => 'current',
      'disaggregation' => 'false',
    ];
  }

  /**
   * Get all attachments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param array $filter
   *   Optional array for filtering the attachments.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  private function getAttachments(ContentEntityInterface $context_object = NULL, array $filter = []) {
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    $attachments = $data->attachments;

    $supported_contexts = [
      'plan_entity' => 'planEntities',
      'governing_entity' => 'governingEntities',
    ];

    if ($context_object && array_key_exists($context_object->bundle(), $supported_contexts)) {
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
