<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\EndpointQuery\ClusterQuery;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;

/**
 * Helper trait for cluster restriction on configuration item plugins.
 */
trait ConfigurationItemClusterRestrictTrait {

  /**
   * Build the cluster restrict form element.
   *
   * @param array $default_value
   *   The default value for the element.
   *
   * @return array
   *   A form element array.
   */
  public function buildClusterRestrictFormElement(?array $default_value = NULL) {
    return [
      '#type' => 'cluster_restrict',
      '#title' => $this->t('Restrict by cluster'),
      '#default_value' => $default_value,
      '#ajax' => property_exists($this, 'wrapperId') ? [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ] : NULL,
    ];
  }

  /**
   * Get a value using the configured cluster restrict.
   *
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   * @param \Drupal\ghi_plans\Plugin\EndpointQuery\ClusterQuery $cluster_query
   *   A query object for the cluster endpoint.
   * @param \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery $flow_search_query
   *   A query object for the plan cluster summary data.
   *
   * @return mixed|null
   *   The retrieved value.
   */
  public function getClusterIdsByClusterRestrict(array $cluster_restrict, ClusterQuery $cluster_query, FlowSearchQuery $flow_search_query): ?array {
    if ($cluster_restrict['type'] == 'none') {
      return NULL;
    }

    $context = $this->getContext();
    $plan_node = $context['plan_object'];
    if (!$plan_node instanceof Plan) {
      return NULL;
    }

    // First extract the cluster ids for the given cluster tag, as used for
    // the specific plan.
    $tagged_clusters = $cluster_query->getTaggedClustersForPlan($plan_node->getSourceId(), $cluster_restrict['tag']);
    $cluster_ids_tagged = array_keys($tagged_clusters);

    // Get all cluster ids in the current context.
    $cluster_ids_all = $flow_search_query->getClusterIds();

    // Now apply the logic.
    $cluster_ids = match ($cluster_restrict['type']) {
      'tag_include' => array_intersect($cluster_ids_all, $cluster_ids_tagged),
      'tag_exclude' => array_diff($cluster_ids_all, $cluster_ids_tagged),
    };
    return $cluster_ids;
  }

  /**
   * Apply a cluster restrict config set to a list of plan entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   A list of entity objects.
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   The filtered list of entities.
   */
  public function applyClusterRestrictFilterToEntities(array $entities, array $cluster_restrict) {
    if (empty($entities) || $cluster_restrict['type'] == 'none') {
      return $entities;
    }
    foreach ($entities as $key => $entity) {
      $tags = $entity->getTags();
      if (empty($tags) && $cluster_restrict['type'] == 'tag_include') {
        // The entity has no tags, so the requested tag can't be there.
        unset($entities[$key]);
        continue;
      }
      if ($cluster_restrict['type'] == 'tag_include' && !in_array(strtolower($cluster_restrict['tag']), $tags)) {
        // The requested tag is not part of the entity tags and tag inclusion
        // has been requested.
        unset($entities[$key]);
        continue;
      }
      if ($cluster_restrict['type'] == 'tag_exclude' && in_array(strtolower($cluster_restrict['tag']), $tags)) {
        // The requested tag is part of the entity tags and tag exlusion has
        // been requested.
        unset($entities[$key]);
        continue;
      }
    }
    return $entities;
  }

}
