<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Helper trait classes that need to generate link to FTS.
 */
trait FtsLinkTrait {

  /**
   * Build a link to FTS Public.
   *
   * @param string|array $label
   *   The label for the link.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan base object.
   * @param string $type
   *   A valid display in FTS Public, e.g. 'summary', 'recipients', 'flows' or
   *   'clusters'.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   A node object that represents the current context.
   *
   * @return array
   *   A fully build HTML link.
   */
  public static function buildFtsLink($label, Plan $plan, $type, BaseObjectInterface $base_object = NULL) {
    $plan_id = $plan->getSourceId();
    $query_args = [];

    if (!empty($base_object) && is_object($base_object) && $base_object instanceof GoverningEntity) {
      // Cluster context.
      $cluster_id = $base_object->getSourceId();
      $cluster_query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('cluster_query');
      $cluster = $cluster_query->getCluster($plan_id, $cluster_id);
      if ($cluster && !empty($cluster->id) && !empty($cluster->name)) {
        $query_args['f'] = ['destinationClusterIdName:' . $cluster->id . ':' . $cluster->name . ''];
      }
    }
    elseif ($base_object === FALSE) {
      $query_args['f'] = ['destinationClusterIdName:!'];
    }
    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => Url::fromUri('https://fts.unocha.org/appeals/' . $plan_id . '/' . $type, [
        'query' => $query_args,
        'html' => TRUE,
      ]),
      '#attributes' => [
        'target' => '_blank',
        'class' => [
          'fts-link',
          'fts-plan-link',
        ],
      ],
    ];
  }

}
