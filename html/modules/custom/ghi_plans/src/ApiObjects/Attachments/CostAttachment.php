<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Abstraction for API cost attachment objects.
 */
class CostAttachment extends Attachment {

  /**
   * Extract the metric values from an attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[] $totals
   *   The totals to use for value extraction.
   *
   * @return array
   *   Array with values for each metric and measurement data point.
   */
  protected function extractValues(array $totals = []): array {
    $values = [];
    foreach ($totals as $item) {
      if (!$item->isTotal()) {
        continue;
      }
      $revision_state = $item->getRevisionState();
      if (!$revision_state) {
        continue;
      }
      $values[$item->getMetric()->getMachineName() . '_' . $revision_state->getMachineName()] = $item->getValue();
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotals(): array {
    $totals = parent::getTotals();
    return array_filter($totals, fn ($fact) => $fact->isTotal());
  }

  /**
   * Get the requirements.
   *
   * @return float|null
   *   The requirements or NULL.
   */
  public function getRequirements(): ?float {
    $values = $this->getPlanningValues();
    $requirements = $values['requirements_current'] ?? $this->getOriginalRequirements();
    return $requirements ? (float) $requirements : NULL;
  }

  /**
   * Get the original requirements.
   *
   * @return float|null
   *   The original requirements or NULL.
   */
  public function getOriginalRequirements(): ?float {
    $values = $this->getPlanningValues();
    $requirements_original = $values['requirements_original'] ?? NULL;
    return $requirements_original ? (float) $requirements_original : NULL;
  }

  /**
   * Get the coverage for a plan based on the given funding.
   *
   * @param float $funding
   *   The funding to calculate the coverage against.
   *
   * @return float
   *   The coverage for a plan.
   */
  public function getCoverage(float $funding): float {
    return (float) CommonHelper::calculateRatio($funding, $this->getRequirements()) * 100;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?AttachmentPrototype {
    return NULL;
  }

}
