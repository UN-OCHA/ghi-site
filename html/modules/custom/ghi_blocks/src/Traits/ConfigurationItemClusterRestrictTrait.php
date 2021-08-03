<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_plans\Query\ClusterQuery;

/**
 * Helper trait for cluster restriction on configurtion item plugins.
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
  public function buildClusterRestrictFormElement(array $default_value = NULL) {
    return [
      '#type' => 'cluster_restrict',
      '#title' => $this->t('Restrict by cluster'),
      '#default_value' => $default_value,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
  }

  /**
   * Get a value using the configured cluster restrict.
   *
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   * @param object $search_results
   *   A result object from the custom search endpoint.
   * @param \Drupal\ghi_plans\Query\ClusterQuery $clusterQuery
   *   A query object for the cluster endpoint.
   *
   * @return mixed|null
   *   The retrieved value.
   */
  public function getClusterIdsByClusterRestrict(array $cluster_restrict, $search_results, ClusterQuery $clusterQuery) {
    if ($cluster_restrict['type'] == 'none') {
      return NULL;
    }

    $context = $this->getContext();
    $plan_node = $context['plan_node'];
    $plan_id = $plan_node->field_original_id->value;

    // First extract the cluster ids for the given cluster tag, as used for
    // the specific plan.
    $cluster_ids = [];
    $tagged_clusters = $clusterQuery->getTaggedClustersForPlan($plan_id, $cluster_restrict['tag']);
    if (!empty($tagged_clusters)) {
      // Get the cluster ids that are actually part of the result set.
      $cluster_ids = $this->flowSearchQuery->getClusterIds($search_results, array_keys($tagged_clusters));
    }

    if ($cluster_restrict['type'] == 'tag_exclude') {
      $cluster_ids_all = $this->flowSearchQuery->getClusterIds($search_results);
      $cluster_ids = array_diff($cluster_ids_all, $cluster_ids);
    }
    return $cluster_ids;
  }

  /**
   * Apply a cluster restrict config set to a list of plan entities.
   *
   * @param object[] $entities
   *   A list of entity objects.
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return array
   *   The filtered list of entities.
   */
  public function applyClusterRestrictFilterToEntities(array $entities, array $cluster_restrict) {
    if (empty($entities) || $cluster_restrict['type'] == 'none') {
      return $entities;
    }
    foreach ($entities as $key => $entity) {
      if (empty($entity->tags) && $cluster_restrict['type'] == 'tag_include') {
        // The entity has no tags, so the requested tag can't be there.
        unset($entities[$key]);
        continue;
      }
      // Make all tags lowercase for comparison.
      $tags = array_map('strtolower', $entity->tags);
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
