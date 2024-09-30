<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Helper trait classes that need to generate link to FTS.
 */
trait FtsLinkTrait {

  /**
   * Build a url to FTS Public.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan base object.
   * @param string $type
   *   A valid display in FTS Public, e.g. 'summary', 'recipients', 'flows' or
   *   'clusters'.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   A base object that represents the current context, e.g. a governing
   *   entity.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function buildFtsUrl(Plan $plan, $type, ?BaseObjectInterface $base_object = NULL) {
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
    elseif ($base_object === NULL && $type != 'summary') {
      $query_args['f'] = ['destinationClusterIdName:!'];
    }
    return Url::fromUri('https://fts.unocha.org/plans/' . $plan_id . '/' . $type, array_filter([
      'query' => $query_args,
    ]));
  }

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
  public static function buildFtsLink($label, Plan $plan, $type, ?BaseObjectInterface $base_object = NULL) {
    $fts_link_title = t('Link to the @plan_name page in FTS', [
      '@plan_name' => $plan->label(),
    ]);

    $url = self::buildFtsUrl($plan, $type, $base_object);
    $url->setOption('html', TRUE);
    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => $url,
      '#attributes' => [
        'target' => '_blank',
        'aria-label' => $fts_link_title,
        'class' => [
          'fts-link',
          'fts-plan-link',
        ],
      ],
    ];
  }

}
