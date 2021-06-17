<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Url;

/**
 * Helper trait classes that need to generate link to FTS.
 */
trait FtsLinkTrait {

  /**
   * Build a link to FTS Public.
   *
   * @param string $label
   *   The label for the link.
   * @param object $plan_node
   *   A plan node object.
   * @param string $type
   *   A valid display in FTS Public, e.g. 'recipients', 'flows' or 'clusters'.
   * @param object $context_node
   *   A node object that represents the current context.
   *
   * @return string
   *   A fully build HTML link.
   */
  public static function buildFtsLink($label, $plan_node, $type, $context_node = NULL) {
    $plan_id = $plan_node->field_original_id->value;
    $query_args = [];

    if (!empty($context_node) && is_object($context_node) && $context_node->bundle() == 'governing_entity') {
      // Cluster context.
      $cluster_id = $context_node->field_original_id->value;
      $cluster_query = \Drupal::service('ghi_plans.cluster_query');
      $cluster = $cluster_query->getCluster($plan_id, $cluster_id);
      if ($cluster && !empty($cluster->id) && !empty($cluster->name)) {
        $query_args['f'] = ['destinationClusterIdName:' . $cluster->id . ':' . $cluster->name . ''];
      }
    }
    elseif ($context_node === FALSE) {
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
