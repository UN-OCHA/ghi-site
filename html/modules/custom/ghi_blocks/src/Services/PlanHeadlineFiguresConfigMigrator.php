<?php

namespace Drupal\ghi_blocks\Services;

use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\PlanMetricData;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\FabricQueryManager;

/**
 * Migrates legacy plan-level headline figures to source-aware metrics.
 */
final class PlanHeadlineFiguresConfigMigrator {

  /**
   * The attachment query.
   */
  private AttachmentQuery $attachmentQuery;

  /**
   * Effective Country Module caseloads keyed by plan source ID.
   *
   * @var array<int, \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface|null>
   */
  private array $effectiveCaseloads = [];

  /**
   * Constructs the configuration migrator.
   */
  public function __construct(FabricQueryManager $fabric_query_manager) {
    $this->attachmentQuery = $fabric_query_manager->createInstance('attachment');
  }

  /**
   * Migrates compatible items in one headline figures block configuration.
   */
  public function migrateConfiguration(array &$configuration, Plan $plan): bool {
    if (!isset($configuration['hpc']['key_figures']['items']) || !is_array($configuration['hpc']['key_figures']['items'])) {
      return FALSE;
    }

    $items = &$configuration['hpc']['key_figures']['items'];
    $changed = FALSE;
    foreach ($items as &$item) {
      $migrated_config = match ($item['item_type'] ?? NULL) {
        'attachment_data' => $this->migrateAttachmentData($item['config'] ?? [], $plan),
        'funding_data' => $this->migrateFundingData($item['config'] ?? []),
        default => NULL,
      };
      if ($migrated_config === NULL) {
        continue;
      }
      $item['item_type'] = 'plan_metric_data';
      $item['config'] = $migrated_config;
      $changed = TRUE;
    }
    unset($item);
    return $changed;
  }

  /**
   * Migrates an attachment item when it uses the effective plan caseload.
   */
  private function migrateAttachmentData(array $configuration, Plan $plan): ?array {
    $attachment_id = $configuration['attachment']['attachment_id'] ?? NULL;
    if (is_array($attachment_id)) {
      $attachment_id = array_key_first($attachment_id);
    }
    if (!is_numeric($attachment_id)) {
      return NULL;
    }

    $attachment = $this->getEffectiveCaseload($plan);
    if (!$attachment instanceof Attachment || (int) $attachment_id !== (int) $attachment->id()) {
      return NULL;
    }

    $data_point = $configuration['data_point'] ?? [];
    $attachment->handleKnownConfigIssues($data_point);
    $processing = $data_point['processing'] ?? DataPointHelper::PROCESSING_SINGLE;
    if (!array_key_exists($processing, DataPointHelper::getProcessingOptions())) {
      return NULL;
    }
    if (!empty($data_point['widget']) && $data_point['widget'] !== 'none') {
      return NULL;
    }

    $metric = $this->getMigratableMetric($data_point['data_points'][0] ?? []);
    if ($metric === NULL) {
      return NULL;
    }

    $migrated = [
      'metric' => $metric,
      'processing' => $processing,
      'formatting' => $data_point['formatting'] ?? DataPointHelper::FORMATTING_AUTO,
      'label' => $data_point['label'] ?? $configuration['label'] ?? '',
    ];
    if (!array_key_exists($migrated['formatting'], DataPointHelper::getFormattingOptions())) {
      return NULL;
    }

    if ($processing === DataPointHelper::PROCESSING_CALCULATED) {
      $metric_2 = $this->getMigratableMetric($data_point['data_points'][1] ?? []);
      $calculation = $data_point['calculation'] ?? NULL;
      if ($metric_2 === NULL || !array_key_exists($calculation, DataPointHelper::getCalculationOptions())) {
        return NULL;
      }
      $migrated['metric_2'] = $metric_2;
      $migrated['calculation'] = $calculation;
    }

    // Preserve attachment-provided labels instead of replacing them with the
    // normalized metric label when no explicit override was saved.
    if ($migrated['label'] === '') {
      $attachment_metric = $data_point['data_points'][0]['metric_type'] ?? $metric;
      $migrated['label'] = $attachment->getPrototype()?->getDefaultFieldLabel($attachment_metric, $plan->getPlanLanguage()) ?? '';
    }
    return $migrated;
  }

  /**
   * Migrates an unrestricted plan-level financial item.
   */
  private function migrateFundingData(array $configuration): ?array {
    $restriction_type = $configuration['cluster_restrict']['type'] ?? NULL;
    if (!in_array($restriction_type, [NULL, '', 'none'], TRUE)) {
      return NULL;
    }
    $migrated = match ($configuration['data_type'] ?? NULL) {
      'funding_totals' => ['metric' => 'funding'],
      'outside_funding' => ['metric' => 'outside_funding'],
      'funding_coverage' => ['metric' => 'coverage'],
      'funding_gap' => ['metric' => 'funding_gap'],
      'original_requirements' => ['metric' => 'original_requirements'],
      'current_requirements' => ['metric' => 'requirements'],
      default => NULL,
    };
    if ($migrated === NULL) {
      return NULL;
    }
    return $migrated + [
      'processing' => DataPointHelper::PROCESSING_SINGLE,
      'formatting' => DataPointHelper::FORMATTING_AUTO,
      'label' => $configuration['label'] ?? '',
    ];
  }

  /**
   * Gets a normalized metric from a current-period attachment data point.
   */
  private function getMigratableMetric(array $data_point): ?string {
    $monitoring_period = $data_point['monitoring_period'] ?? NULL;
    if (!in_array($monitoring_period, [NULL, '', 'latest'], TRUE)) {
      return NULL;
    }
    $metric = PlanMetricResolver::normalizeMetricName($data_point['metric_type'] ?? NULL);
    return in_array($metric, PlanMetricData::SUPPORTED_METRICS, TRUE) ? $metric : NULL;
  }

  /**
   * Gets the effective Country Module caseload for a plan.
   */
  private function getEffectiveCaseload(Plan $plan): ?CaseloadAttachmentInterface {
    $plan_id = $plan->getSourceId();
    if (!$plan_id) {
      return NULL;
    }
    if (!array_key_exists($plan_id, $this->effectiveCaseloads)) {
      $attachments = $this->attachmentQuery->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_PLAN, $plan_id, 'caseload');
      $this->effectiveCaseloads[$plan_id] = $plan->getPlanCaseload($attachments);
    }
    return $this->effectiveCaseloads[$plan_id];
  }

}
