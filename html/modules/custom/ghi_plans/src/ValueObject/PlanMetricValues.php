<?php

namespace Drupal\ghi_plans\ValueObject;

/**
 * Normalized plan-level metrics for one configured source.
 *
 * The source controls selectable plan metrics. Canonical metrics such as FTS
 * funding can be included independently of that setting.
 *
 * Cache tags travel with the values because normalization otherwise hides the
 * Fabric dependencies from render and aggregate consumers.
 */
final class PlanMetricValues {

  /**
   * Constructs a normalized plan metric set.
   *
   * @param string $source
   *   The configured source used to resolve source-selectable values.
   * @param array $values
   *   Metric values keyed by stable metric name.
   * @param array $caseloads
   *   Country Module caseload objects retained for attachment field access and
   *   configured caseload overrides.
   * @param string[] $cacheTags
   *   Cache tags for the source data used to build these values.
   */
  public function __construct(private readonly string $source, private readonly array $values = [], private readonly array $caseloads = [], private readonly array $cacheTags = []) {
  }

  /**
   * Gets the source used for source-selectable values in this metric set.
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Checks whether a metric was supplied by the selected source.
   */
  public function has(string $metric): bool {
    return array_key_exists($metric, $this->values);
  }

  /**
   * Gets one normalized metric value.
   */
  public function get(string $metric): int|float|null {
    return $this->values[$metric] ?? NULL;
  }

  /**
   * Gets all normalized metric values.
   */
  public function getValues(): array {
    return $this->values;
  }

  /**
   * Gets Country Module caseloads needed by attachment-based consumers.
   */
  public function getCaseloads(): array {
    return $this->caseloads;
  }

  /**
   * Gets cache tags for the source data used by this metric set.
   */
  public function getCacheTags(): array {
    return $this->cacheTags;
  }

}
