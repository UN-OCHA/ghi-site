<?php

namespace Drupal\ghi_plans\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * Controller for autocomplete plan loading.
 */
class PlanAutocompleteController extends ControllerBase {

  /**
   * Handler for fetching plans using autocomplete.
   */
  public function planAutocomplete(Request $request) {
    $matches = [];
    $string = $request->query->get('q');

    if (empty($string)) {
      return new JsonResponse($matches);
    }

    $nodes = NodeHelper::getNodesFromTitle($string, 'plan');
    if (!empty($nodes)) {
      // Sort by year.
      uasort($nodes, function ($a, $b) {
        $year_a = $a->field_plan_year->value;
        $year_b = $b->field_plan_year->value;
        return $year_a == $year_b ? 0 : (($year_a > $year_b) ? -1 : 1);
      });
      foreach ($nodes as $node) {
        $title = $node->getTitle();
        $plan_id = NodeHelper::getOriginalIdFromNode($node);
        // @codingStandardsIgnoreStart
        // if (!empty($hidden_plan_ids) && in_array($plan_id, $hidden_plan_ids)) {
        //   continue;
        // }
        // @codingStandardsIgnoreEnd

        $matches[] = [
          'value' => $title,
          'label' => $title . '(' . $plan_id . ')',
        ];
      }
    }

    return new JsonResponse($matches);
  }

}
