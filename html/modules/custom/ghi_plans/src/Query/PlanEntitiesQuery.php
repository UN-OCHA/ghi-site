<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\node\NodeInterface;

/**
 * Query class for fetching plan data with a focus on plan entities.
 */
class PlanEntitiesQuery extends EndpointQuery {

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
   * @param \Drupal\node\NodeInterface $context_node
   *   The current context node.
   * @param string $type
   *   Optional type for filtering the attachments.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getAttachments(NodeInterface $context_node = NULL, $type = NULL) {
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    $attachments = [];
    $attachments = $data->attachments;

    $supported_contexts = [
      'plan_entity' => 'planEntities',
      'governing_entity' => 'governingEntities',
    ];

    if ($context_node && array_key_exists($context_node->bundle(), $supported_contexts)) {
      $context_original_id = $context_node->field_original_id->value;
      $property = $supported_contexts[$context_node->bundle()];
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

    if ($type === NULL) {
      return $attachments;
    }
    $filtered_attachments = [];
    foreach ($attachments as $attachment) {
      if (strtolower($attachment->type) != strtolower($type)) {
        continue;
      }
      $filtered_attachments[] = $attachment;
    }
    return $filtered_attachments;
  }

  /**
   * Get webcontent file attachments.
   *
   * @param \Drupal\node\NodeInterface $context_node
   *   The current context node.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getWebContentFileAttachments(NodeInterface $context_node) {
    $attachments = [];
    foreach ($this->getAttachments($context_node, 'fileWebContent') as $attachment) {
      if (empty($attachment->attachmentVersion->value->file->url)) {
        continue;
      }
      $attachments[] = (object) [
        'id' => $attachment->id,
        'url' => $attachment->attachmentVersion->value->file->url,
        'title' => $attachment->attachmentVersion->value->file->title ?? '',
        'file_name' => $attachment->attachmentVersion->value->name ?? '',
      ];
    }
    return $attachments;
  }

  /**
   * Get webcontent text attachments.
   *
   * @param \Drupal\node\NodeInterface $context_node
   *   The current context node.
   *
   * @return array
   *   An array of attachment objects for the given context.
   */
  public function getWebContentTextAttachments(NodeInterface $context_node) {
    $attachments = [];
    foreach ($this->getAttachments($context_node, 'textWebContent') as $attachment) {
      if (empty($attachment->attachmentVersion->value->content ?? '')) {
        continue;
      }
      $attachments[] = (object) [
        'id' => $attachment->id,
        'title' => $attachment->attachmentVersion->value->name,
        'content' => html_entity_decode($attachment->attachmentVersion->value->content ?? ''),
      ];
    }
    return $attachments;
  }

  /**
   * Get available plan entities for the given context.
   *
   * @param \Drupal\node\NodeInterface $context_node
   *   The current context node.
   * @param string $entity_type
   *   The entity type to restrict the context.
   *
   * @return array
   *   An array of plan entity objects for the given context.
   */
  public function getPlanEntities(NodeInterface $context_node, $entity_type = NULL) {
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    if ($entity_type === NULL) {
      $filter = [
        'entityPrototype.type' => 'PE',
      ];
    }
    else {
      $filter = [
        'entityPrototype.refCode' => $entity_type,
      ];
    }

    $matching_entities = ApiEntityHelper::getMatchingPlanEntities($this->getData(), $context_node->bundle() != 'plan' ? $context_node : NULL, NULL, $filter);
    if (empty($matching_entities)) {
      return NULL;
    }
    return array_map(function ($entity) {
      $entity_version = ApiEntityHelper::getEntityVersion($entity);
      return (object) [
        'id' => $entity->id,
        'plural_name' => $entity->entityPrototype->value->name->en->plural,
        'order_number' => $entity->entityPrototype->orderNumber,
        'ref_code' => $entity->entityPrototype->refCode,
        'custom_id' => $entity_version->customReference,
        'custom_id_prefixed_refcode' => $entity->entityPrototype->refCode . $entity_version->customReference,
        'composed_reference' => $entity->composedReference,
        'description' => $entity_version->value->description,
      ];
    }, $matching_entities);
  }

}
