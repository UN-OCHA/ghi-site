<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\Entity\Plan;

/**
 * Provides plan-language translation options with an English fallback.
 */
trait PlanTranslationOptionsTrait {

  /**
   * Builds translation options for frontend strings tied to a plan.
   *
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan
   *   The plan supplying the frontend language, or NULL for English.
   * @param string|null $context
   *   An optional translation context.
   *
   * @return array
   *   Translation options containing the plan language and optional context.
   */
  protected function buildPlanTranslationOptions(?Plan $plan = NULL, ?string $context = NULL): array {
    return Plan::buildTranslationOptions($plan, $context);
  }

}
