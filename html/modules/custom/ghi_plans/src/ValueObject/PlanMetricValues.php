<?php

namespace Drupal\ghi_plans\ValueObject;

use Drupal\Core\Cache\Cache;

/**
 * Normalized plan-level metrics for one plan.
 *
 * The primary source controls standard selectable metrics. Prioritized values
 * may come from the plan's explicit prioritized source override, and canonical
 * metrics such as FTS funding can be included independently of either setting.
 *
 * Cache tags travel with the values because normalization otherwise hides the
 * Fabric dependencies from render and aggregate consumers.
 */
final class PlanMetricValues {

  /**
   * Constructs a normalized plan metric set.
   *
   * @param string $source
   *   The primary configured source used to resolve standard metric values.
   * @param array $values
   *   Metric values keyed by stable metric name.
   * @param array $caseloads
   *   Country Module caseload objects retained for attachment field access and
   *   configured caseload overrides.
   * @param string[] $cacheTags
   *   Cache tags for the source data used to build these values.
   * @param bool|null $sourceValuesAvailable
   *   Whether the selected source supplied values before source-independent
   *   metrics were added. NULL infers availability from the complete set.
   * @param string[] $metricSources
   *   Optional source overrides keyed by normalized metric name.
   */
  public function __construct(private readonly string $source, private readonly array $values = [], private readonly array $caseloads = [], private readonly array $cacheTags = [], private readonly ?bool $sourceValuesAvailable = NULL, private readonly array $metricSources = []) {
  }

  /**
   * Gets the primary source used for standard metrics in this metric set.
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Gets the source used for one normalized metric.
   */
  public function getSourceForMetric(string $metric): string {
    return $this->metricSources[$metric] ?? $this->source;
  }

  /**
   * Replaces selected metrics with values from another resolved metric set.
   *
   * The selected source is recorded even when it did not supply a value. This
   * lets consumers distinguish missing data from permission to use another
   * retained source object as a fallback.
   *
   * @param self $overrides
   *   The resolved values from the overriding source.
   * @param string[] $metrics
   *   The normalized metrics controlled by that source.
   *
   * @return self
   *   A new metric set containing the replacements.
   */
  public function withMetricOverrides(self $overrides, array $metrics): self {
    $values = $this->values;
    $metric_sources = $this->metricSources;
    foreach ($metrics as $metric) {
      unset($values[$metric]);
      if ($overrides->has($metric)) {
        $values[$metric] = $overrides->get($metric);
      }
      $metric_sources[$metric] = $overrides->getSourceForMetric($metric);
    }
    return new self(
      $this->source,
      $values,
      $this->caseloads + $overrides->getCaseloads(),
      Cache::mergeTags($this->cacheTags, $overrides->getCacheTags()),
      $this->sourceValuesAvailable,
      $metric_sources,
    );
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
   * Checks whether the primary plan metric source supplied any values.
   *
   * This excludes source-independent values such as canonical FTS funding
   * when the resolver explicitly records availability.
   */
  public function hasSourceValues(): bool {
    return $this->sourceValuesAvailable ?? !empty($this->values);
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
