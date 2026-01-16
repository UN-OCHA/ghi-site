<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Provides a query plugin for plan funding summary.
 *
 * @EndpointQuery(
 *   id = "plan_funding_summary_query",
 *   label = @Translation("Plan funding summary query"),
 *   endpoint = {
 *     "public" = "fts/flow/plan/summary/{plan_id}",
 *     "version" = "v1"
 *   }
 * )
 */
class PlanFundingSummaryQuery extends EndpointQueryBase {

  /**
   * This holds the processed data.
   *
   * @var array
   */
  private $data = NULL;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $data = (array) parent::getData($placeholders, $query_args);
    $data += [
      'total_funding' => 0,
      'overall_funding' => 0,
    ];
    return [
      'total_funding' => $data['total_funding'],
      'overall_funding' => $data['overall_funding'],
    ];
  }

  /**
   * Get a specific property from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property or a default value.
   */
  public function get($property, $default) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    return !empty($this->data[$property]) ? $this->data[$property] : $default;
  }

  /**
   * Get the total funding.
   *
   * @param int $default
   *   Optional default value.
   *
   * @return float
   *   The total funding value.
   */
  public function getTotalFunding($default = 0): float {
    return (float) $this->get('total_funding', $default);
  }

  /**
   * Get the overall funding.
   *
   * @param int $default
   *   Optional default value.
   *
   * @return float
   *   The overall funding value.
   */
  public function getOverallFunding($default = 0): float {
    return (float) $this->get('overall_funding', $default);
  }

  /**
   * Get the outside funding.
   *
   * @return float
   *   The outside funding value.
   */
  public function getOutsideFunding(): float {
    $total_funding = $this->getTotalFunding();
    $overall_funding = $this->getOverallFunding();
    return $overall_funding - $total_funding;
  }

  /**
   * Get the funding gap.
   *
   * @param float $requirements
   *   The requirements to compare against.
   *
   * @return float
   *   The funding gap value.
   */
  public function getFundingGap(float $requirements): float {
    $total_funding = $this->getTotalFunding();
    return $requirements - $total_funding;
  }

  /**
   * Get the funding coverage.
   *
   * @param float $requirements
   *   The requirements to compare against.
   *
   * @return float
   *   The funding coverage value.
   */
  public function getFundingCoverage(float $requirements): float {
    $funding = $this->getTotalFunding();
    return (float) CommonHelper::calculateRatio($funding, $requirements) * 100;
  }

}
