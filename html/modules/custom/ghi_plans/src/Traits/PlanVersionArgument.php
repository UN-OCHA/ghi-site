<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;

/**
 * Trait to help with plan version argument.
 */
trait PlanVersionArgument {

  /**
   * Get the plan version argument for the given plan id.
   *
   * @param int $plan_id
   *   The original id of the plan.
   *
   * @return string
   *   The version argument as a string for the API.
   */
  public static function getPlanVersionArgumentForPlanId($plan_id) {
    if (self::getCurrentUser()->isAnonymous()) {
      return 'current';
    }
    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId($plan_id, 'plan');
    return $base_object?->get('field_plan_version_argument')->value ?? 'current';
  }

  /**
   * Wrapper around Drupal::currentUser().
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The AccountProxy object of the current user.
   */
  public static function getCurrentUser() {
    return \Drupal::currentUser();
  }

}
