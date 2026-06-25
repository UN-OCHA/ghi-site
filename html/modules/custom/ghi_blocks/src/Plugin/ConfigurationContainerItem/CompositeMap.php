<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\MapMetricAvailabilityHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerItemCustomActionTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a composite map dataset for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'composite_map',
  label: new TranslatableMarkup('Composite Map'),
  description: new TranslatableMarkup('This item represents a composite map.'),
)]
class CompositeMap extends ConfigurationContainerItemPluginBase implements ConfigurationContainerItemCustomActionsInterface {

  use ConfigurationContainerItemCustomActionTrait;
  use DisaggregatedDataTrait;
  use PlanReportingPeriodTrait;

  const MAX_SLICES = 3;
  const NONE = -1;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * Transformed metric items keyed by attachment and reporting period.
   *
   * @var array
   */
  private array $metricItems = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): CompositeMap {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $instance->fabricQueryManager->createInstance('attachment');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'dataset_form' => (string) $this->t('Datasets'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isValidAction($action) {
    if (!array_key_exists($action, $this->getCustomActions())) {
      return FALSE;
    }
    return !empty($this->getContextAttachments());
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#required'] = TRUE;

    if ($this->getFullPieMetricType() === NULL) {
      $element['#submit_redirect_custom_action'] = 'dataset_form';
    }

    return $element;
  }

  /**
   * Form callback for the custom action "dataset".
   */
  public function datasetForm($element, FormStateInterface $form_state, $default_values = NULL) {
    $attachments = $this->getContextAttachments();
    ArrayHelper::sortObjectsByStringProperty($attachments, 'getComposedReference');

    $element['datasets'] = [
      '#type' => 'map_dataset',
      '#attachment_ids' => array_keys($attachments),
      '#default_value' => $default_values['datasets'] ?? NULL,
      '#dataset_id' => $element['#item_id'],
    ];
    return $element;
  }

  /**
   * Get an ID for a dataset.
   */
  public function getId(): string {
    return Html::getId($this->getLabel());
  }

  /**
   * Build the map data for this configuration item.
   */
  public function buildMapData(): ?array {
    if ($this->getFullPieMetricType() === NULL) {
      return NULL;
    }

    $attachments = $this->getAttachments();
    if (empty($attachments)) {
      return NULL;
    }

    $map_data = [
      'label' => $this->getLabel(),
      'locations' => array_values($this->buildLocations()),
      'full_pie' => $this->buildMapDataForItemConfig($this->getFullPieConfig(), TRUE),
      'polygon' => $this->buildMapDataForItemConfig($this->getPolygonConfig()),
      'slices' => array_values(array_filter(array_map(function ($slice) {
        return $this->buildMapDataForItemConfig($slice);
      }, $this->getSlicesConfig() ?? []))),
    ];

    return !empty($map_data['locations']) && !empty($map_data['full_pie']) ? $map_data : NULL;
  }

  /**
   * Build the map data for the given item.
   */
  private function buildMapDataForItemConfig(?array $config, bool $is_base_data = FALSE): ?array {
    if (empty($config['attachment']) || empty($config['metric']) || $config['metric'] == self::NONE) {
      return NULL;
    }
    $attachment = $this->attachmentQuery->getAttachment((int) $config['attachment']);
    if (!$attachment instanceof Attachment) {
      return NULL;
    }

    $reporting_period_id = $config['settings']['monitoring_period'] ?? 'latest';
    $metric_item = $this->getMetricItem($attachment, $config['metric'], $reporting_period_id);
    if (!$metric_item) {
      return NULL;
    }

    $metric_type = $metric_item['metric_object']->getMachineName();
    $reporting_period = $this->getPlanReportingPeriod($attachment->getPlanId(), $reporting_period_id);
    return [
      'is_base_data' => $is_base_data,
      // The client still uses the old key name, but this is now a metric type.
      'metric_index' => $metric_type,
      'metric_type' => $metric_type,
      'metric_label' => ($config['settings']['label'] ?? NULL) ?: $metric_item['metric_object']->getLabel($attachment->getPlanObject()?->getPlanLanguage() ?? 'en'),
      'unit_type' => $metric_item['unit_type'],
      'monitoring_period' => $reporting_period && $metric_item['is_measurement'] ? $reporting_period->format('Monitoring period #@period_number<br>@date_range') : NULL,
      'attachment' => [
        'id' => $attachment->id(),
        'title' => $attachment->getTitle(),
      ],
    ];
  }

  /**
   * Get a metric item from an attachment.
   */
  private function getMetricItem(Attachment $attachment, string|int $metric, int|string $reporting_period_id = 'latest'): ?array {
    $metric_type = $this->normalizeMetricType($attachment, $metric);
    if ($metric_type === NULL) {
      return NULL;
    }
    $metric_items = $this->getMetricItems($attachment, $reporting_period_id);
    return $metric_items[$metric_type] ?? NULL;
  }

  /**
   * Get transformed metric items keyed by metric type.
   */
  private function getMetricItems(Attachment $attachment, int|string $reporting_period_id = 'latest'): array {
    $cache_key = $attachment->id() . ':' . $reporting_period_id;
    if (array_key_exists($cache_key, $this->metricItems)) {
      return $this->metricItems[$cache_key];
    }
    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period_id);
    if (!$disaggregated_data) {
      return $this->metricItems[$cache_key] = [];
    }
    $this->metricItems[$cache_key] = [];
    foreach ($this->transformDisaggregatedMapData($disaggregated_data, $attachment) as $metric_item) {
      $this->metricItems[$cache_key][$metric_item['metric_object']->getMachineName()] = $metric_item;
    }
    return $this->metricItems[$cache_key];
  }

  /**
   * Normalize legacy metric indexes to Fabric metric type machine names.
   */
  private function normalizeMetricType(Attachment $attachment, string|int|null $metric): ?string {
    if ($metric === NULL || $metric == self::NONE) {
      return NULL;
    }
    if (is_numeric($metric)) {
      $field_types = array_values($attachment->getFieldTypes());
      return $field_types[(int) $metric] ?? NULL;
    }
    return $metric;
  }

  /**
   * Get the modal content for the given location and metric label.
   */
  private function getModalContent(array $location, string $metric_label): array {
    $categories = array_filter($location['categories'], function ($category) {
      return $category['data'] !== NULL;
    });
    $category_data = [];
    foreach ($categories as $category_key => $category) {
      $category_data[$category_key] = $category['data'];
    }
    return [
      'total' => $location['total'],
      'metric_label' => $metric_label,
      'categories' => $category_data,
    ];
  }

  /**
   * Build the locations array for the map data.
   */
  private function buildLocations(): array {
    $full_pie_config = $this->getFullPieConfig();
    if (empty($full_pie_config['metric']) || $full_pie_config['metric'] == self::NONE) {
      return [];
    }

    $items = array_filter(array_merge([
      $this->getFullPieConfig(),
      $this->getPolygonConfig(),
    ], $this->getSlicesConfig() ?? []));

    $locations = [];
    $metric_pairs = [];
    foreach ($items as $item) {
      if (empty($item['attachment']) || empty($item['metric']) || $item['metric'] == self::NONE) {
        continue;
      }
      $attachment = $this->attachmentQuery->getAttachment((int) $item['attachment']);
      if (!$attachment instanceof Attachment) {
        continue;
      }
      $reporting_period_id = $item['settings']['monitoring_period'] ?? 'latest';
      $metric_item = $this->getMetricItem($attachment, $item['metric'], $reporting_period_id);
      if (!$metric_item || empty($metric_item['locations'])) {
        continue;
      }
      $metric_type = $metric_item['metric_object']->getMachineName();
      $metric_pairs[$attachment->id()][$metric_type] = TRUE;
      $metric_label = ($item['settings']['label'] ?? NULL) ?: $metric_item['metric_object']->getLabel($attachment->getPlanObject()?->getPlanLanguage() ?? 'en');
      foreach ($metric_item['locations'] as $location_id => $location) {
        if (empty($locations[$location_id])) {
          $locations[$location_id] = $location['map_data'];
        }
        $locations[$location_id]['metrics'][$attachment->id()][$metric_type] = $location['total'];
        $locations[$location_id]['modal_contents'][$attachment->id()][$metric_type] = $this->getModalContent($location, $metric_label);
        unset($locations[$location_id]['total']);
        unset($locations[$location_id]['status']);
        unset($locations[$location_id]['iso3']);
        unset($locations[$location_id]['parent_id']);
        unset($locations[$location_id]['valid_on']);
      }
    }

    foreach ($locations as &$location) {
      foreach ($metric_pairs as $attachment_id => $metric_types) {
        foreach (array_keys($metric_types) as $metric_type) {
          $location['metrics'][$attachment_id][$metric_type] = $location['metrics'][$attachment_id][$metric_type] ?? NULL;
          $location['modal_contents'][$attachment_id][$metric_type] = $location['modal_contents'][$attachment_id][$metric_type] ?? NULL;
        }
      }
    }

    $full_pie_attachment_id = $full_pie_config['attachment'] ?? NULL;
    $full_pie_attachment = $full_pie_attachment_id ? $this->attachmentQuery->getAttachment((int) $full_pie_attachment_id) : NULL;
    $full_pie_metric = $full_pie_attachment instanceof Attachment ? $this->normalizeMetricType($full_pie_attachment, $full_pie_config['metric'] ?? NULL) : NULL;
    if (!$full_pie_attachment || !$full_pie_metric) {
      return [];
    }

    return array_map(function ($location) use ($full_pie_attachment, $full_pie_metric) {
      $location['total'] = $location['metrics'][$full_pie_attachment->id()][$full_pie_metric] ?? 0;
      return $location;
    }, $locations);
  }

  /**
   * Get the attachments from the context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects.
   */
  private function getContextAttachments(): array {
    $context = $this->getContext();
    $attachment_ids = $context['attachment_ids'] ?? [];
    $attachments = $attachment_ids ? $this->attachmentQuery->getAttachmentsById($attachment_ids) : [];
    return array_filter($attachments, function (AttachmentInterface $attachment) {
      return $attachment instanceof Attachment;
    });
  }

  /**
   * Get the configured attachments.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   The attachment objects.
   */
  public function getAttachments(): array {
    $attachments = $this->getContextAttachments();
    if (count($attachments) == 1) {
      return $attachments;
    }
    $attachment_ids = array_unique(array_filter(array_values(array_map(function (array $dataset): int|null {
      return $dataset['attachment'] ?? NULL;
    }, $this->config['dataset_form']['datasets'] ?? []))));
    return array_intersect_key($attachments, array_flip($attachment_ids));
  }

  /**
   * Get the full pie configuration.
   */
  private function getFullPieConfig(): ?array {
    return $this->config['dataset_form']['datasets']['full_pie'] ?? NULL;
  }

  /**
   * Get the polygon configuration.
   */
  private function getPolygonConfig(): ?array {
    return $this->config['dataset_form']['datasets']['polygon'] ?? NULL;
  }

  /**
   * Get the slices configuration.
   */
  private function getSlicesConfig(): ?array {
    $slices = [];
    foreach ($this->config['dataset_form']['datasets']['slices'] ?? [] as $slice) {
      if (!is_array($slice) || !array_key_exists('metric', $slice) || $slice['metric'] == self::NONE) {
        continue;
      }
      $slices[] = $slice;
    }
    return $slices ?: NULL;
  }

  /**
   * Get the metric type for the full pie.
   */
  public function getFullPieMetricType(): ?string {
    $config = $this->getFullPieConfig();
    if (empty($config['attachment'])) {
      return NULL;
    }
    $attachment = $this->attachmentQuery?->getAttachment((int) $config['attachment']);
    return $attachment instanceof Attachment ? $this->normalizeMetricType($attachment, $config['metric'] ?? NULL) : NULL;
  }

  /**
   * Get the metric type for the polygon.
   */
  public function getPolygonMetricType(): ?string {
    $config = $this->getPolygonConfig();
    if (empty($config['attachment'])) {
      return NULL;
    }
    $attachment = $this->attachmentQuery?->getAttachment((int) $config['attachment']);
    return $attachment instanceof Attachment ? $this->normalizeMetricType($attachment, $config['metric'] ?? NULL) : NULL;
  }

  /**
   * Get the metric types for the slices.
   */
  public function getSliceMetricTypes(): array {
    $types = [];
    foreach ($this->getSlicesConfig() ?? [] as $slice) {
      if (empty($slice['attachment'])) {
        continue;
      }
      $attachment = $this->attachmentQuery?->getAttachment((int) $slice['attachment']);
      if ($attachment instanceof Attachment && $metric_type = $this->normalizeMetricType($attachment, $slice['metric'] ?? NULL)) {
        $types[] = $metric_type;
      }
    }
    return $types;
  }

  /**
   * Value callback for the attachment column in the configuration container.
   */
  public function getAttachmentSummary(): string {
    $attachments = $this->getAttachments();
    if (empty($attachments)) {
      return $this->t('Missing');
    }
    return implode(', ', array_map(function (Attachment $attachment) {
      return $attachment->getTitle();
    }, $attachments));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    if ($this->getFullPieMetricType() === NULL) {
      $errors[] = $this->t('No base metric (full pie) selected. The map will not be displayed.');
    }
    elseif ($warning = $this->getMetricAvailabilityWarning($this->getFullPieConfig(), TRUE)) {
      $errors[] = $warning;
    }
    if ($warning = $this->getMetricAvailabilityWarning($this->getPolygonConfig())) {
      $errors[] = $warning;
    }
    foreach ($this->getSlicesConfig() ?? [] as $slice) {
      if ($warning = $this->getMetricAvailabilityWarning($slice)) {
        $errors[] = $warning;
      }
    }
    return $errors;
  }

  /**
   * Get an editor warning for a metric without location-level data.
   */
  private function getMetricAvailabilityWarning(?array $config, bool $required = FALSE): ?TranslatableMarkup {
    if (empty($config['attachment']) || empty($config['metric']) || $config['metric'] == self::NONE) {
      return NULL;
    }
    $attachment = $this->attachmentQuery->getAttachment((int) $config['attachment']);
    if (!$attachment instanceof Attachment) {
      return NULL;
    }
    $metric_type = $this->normalizeMetricType($attachment, $config['metric']);
    $availability = $this->attachmentQuery->getMappableMapMetricAvailability($attachment);
    if (!$metric_type || $availability === NULL) {
      return NULL;
    }

    $label = ($config['settings']['label'] ?? NULL) ?: ($attachment->getFields()[$metric_type] ?? $metric_type);
    return MapMetricAvailabilityHelper::getWarning($attachment, $metric_type, $config['settings'] ?? NULL, $availability, $label, $required);
  }

}
