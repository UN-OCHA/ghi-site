<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerItemCustomActionTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a composite map dataset for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "composite_map",
 *   label = @Translation("Composite Map"),
 *   description = @Translation("This item represets a composite map."),
 * )
 */
class CompositeMap extends ConfigurationContainerItemPluginBase implements ConfigurationContainerItemCustomActionsInterface {

  use ConfigurationContainerItemCustomActionTrait;
  use PlanReportingPeriodTrait;

  const MAX_SLICES = 3;

  const NONE = -1;

  const LABEL_IN_USE = 'Already in use';
  const LABEL_EMPTY = 'Empty';

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * The attachment search query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery
   */
  public $attachmentSearchQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $instance->endpointQueryManager->createInstance('attachment_query');
    $instance->attachmentSearchQuery = $instance->endpointQueryManager->createInstance('attachment_search_query');
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

    $full_pie_index = $this->getFullPieIndex();
    if ($full_pie_index === NULL) {
      // Redirect to the dataset form if no metric for the full pie has been
      // set yet.
      $element['#submit_redirect_custom_action'] = 'dataset_form';
    }

    return $element;
  }

  /**
   * Form callback for the custom action "dataset".
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function datasetForm($element, FormStateInterface $form_state) {
    $element = parent::buildCustomActionForm($element, $form_state);

    $attachments = $this->getContextAttachments();
    ArrayHelper::sortObjectsByStringProperty($attachments, 'composed_reference');
    ArrayHelper::sortObjectsByStringProperty($attachments, 'sort_key');

    $element['datasets'] = [
      '#type' => 'map_dataset',
      '#attachment_ids' => array_keys($attachments),
      '#default_value' => $element['#default_value']['datasets'] ?? NULL,
      '#dataset_id' => $element['#item_id'],
    ];
    return $element;
  }

  /**
   * Get an ID for a dataset.
   *
   * @return string
   *   An id derived from the label.
   */
  public function getId() {
    return Html::getId($this->getLabel());
  }

  /**
   * Build the map data for this configuration item.
   *
   * @return array|null
   *   An array with map data or NULL.
   */
  public function buildMapData(): ?array {
    $full_pie_index = $this->getFullPieIndex();
    if ($full_pie_index === NULL) {
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
      'slices' => array_map(function ($slice) {
        return $this->buildMapDataForItemConfig($slice);
      }, $this->getSlicesConfig() ?? []),
      'variants' => [],
    ];

    return $map_data;
  }

  /**
   * Build the map data for the given item.
   *
   * @param array $config
   *   An array with the item configuration.
   * @param bool $is_base_data
   *   Whether this represents the base data (full pie).
   *
   * @return array|null
   *   An array with the map data or NULL.
   */
  private function buildMapDataForItemConfig($config, $is_base_data = FALSE): ?array {
    $attachment_id = $config['attachment'] ?? NULL;
    if (!$attachment_id) {
      return NULL;
    }
    $reporting_period_id = $config['settings']['monitoring_period'] ?? 'latest';

    $attachment = $this->attachmentQuery->getAttachment($attachment_id, TRUE, $reporting_period_id);
    if (!$attachment instanceof DataAttachmentInterface) {
      return NULL;
    }
    $reporting_period = $this->getPlanReportingPeriod($attachment->getPlanId(), $reporting_period_id);
    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period_id);
    $metric_index = $config['metric'] ?? NULL;
    if ($metric_index === NULL || !isset($disaggregated_data[$metric_index])) {
      return NULL;
    }
    $metric_item = $disaggregated_data[$metric_index] ?? NULL;
    if (!$metric_item) {
      return NULL;
    }
    return [
      'is_base_data' => $is_base_data,
      'metric_index' => $metric_index,
      'metric_label' => ($config['settings']['label'] ?? NULL) ?: $metric_item['metric']->name->en,
      'unit_type' => $metric_item['unit_type'],
      'monitoring_period' => $reporting_period && $metric_item['is_measurement'] ? $reporting_period->format('Monitoring period #@period_number<br>@date_range') : NULL,
      'attachment' => [
        'id' => $attachment->id(),
        'title' => $attachment->getTitle(),
      ],
    ];
  }

  /**
   * Build the locations array for the map data.
   *
   * @return array
   *   An array of locations with map data and metrics.
   */
  private function buildLocations(): array {
    $full_pie_config = $this->getFullPieConfig();

    $items = array_merge([
      $this->getFullPieConfig(),
      $this->getPolygonConfig(),
    ], $this->getSlicesConfig() ?? []);

    if (($full_pie_config['metric'] ?? NULL) === NULL) {
      return [];
    }

    // Prepare the common locations array.
    $locations = [];
    $used_metrics = [];

    $attachment_ids = [];
    foreach ($items as $item) {
      $attachment_id = $item['attachment'] ?? NULL;
      if (!$attachment_id) {
        continue;
      }
      $attachment_ids[] = $attachment_id;
    }

    foreach ($items as $item) {
      $attachment_id = $item['attachment'] ?? NULL;
      if (!$attachment_id) {
        continue;
      }
      $reporting_period_id = $item['settings']['monitoring_period'] ?? 'latest';
      $attachment = $this->attachmentQuery->getAttachment($attachment_id, TRUE, $reporting_period_id);
      if (!$attachment instanceof DataAttachmentInterface) {
        continue;
      }
      $disaggregated_data = $attachment->getDisaggregatedData($reporting_period_id);
      $metric_index = $item['metric'];
      $metric_item = $disaggregated_data[$item['metric']] ?? NULL;
      if (!$metric_item || empty($metric_item['locations'])) {
        continue;
      }
      foreach ($metric_item['locations'] as $location_id => $location) {
        if (empty($locations[$location_id])) {
          $locations[$location_id] = $location['map_data'];
        }
        if (empty($locations[$location_id]['metrics'])) {
          $locations[$location_id]['metrics'] = array_fill_keys($attachment_ids, array_fill_keys(array_keys($used_metrics), NULL));
        }
        $locations[$location_id]['metrics'][$attachment_id][$metric_index] = $location['total'];
        unset($locations[$location_id]['total']);
        unset($locations[$location_id]['status']);
        unset($locations[$location_id]['iso3']);
        unset($locations[$location_id]['parent_id']);
        unset($locations[$location_id]['valid_on']);
      }
      $used_metrics[$metric_index] = $metric_item['metric']->name->en;
    }
    $locations = array_map(function ($location) use ($full_pie_config) {
      $attachment_id = $full_pie_config['attachment'] ?? NULL;
      $location['total'] = $location['metrics'][$attachment_id][$full_pie_config['metric']];
      return $location;
    }, $locations);
    return $locations;
  }

  /**
   * Get the attachments from the context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface[]
   *   An array of attachment objects.
   */
  private function getContextAttachments(): array {
    $context = $this->getContext();
    /** @var int[] $attachment_ids */
    $attachment_ids = $context['attachment_ids'];
    $attachments = $this->attachmentSearchQuery->getAttachmentsById($attachment_ids);
    $attachments = array_filter($attachments, function (AttachmentInterface $attachment) {
      return $attachment instanceof DataAttachmentInterface;
    });
    return $attachments;
  }

  /**
   * Get the configured attachments.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface[]
   *   The attachments object.
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
   *
   * @return array|null
   *   The configuration array or NULL.
   */
  private function getFullPieConfig(): ?array {
    return $this->config['dataset_form']['datasets']['full_pie'] ?? NULL;
  }

  /**
   * Get the polygon configuration.
   *
   * @return array|null
   *   The configuration array or NULL.
   */
  private function getPolygonConfig(): ?array {
    return $this->config['dataset_form']['datasets']['polygon'] ?? NULL;
  }

  /**
   * Get the slices configuration.
   *
   * @return array|null
   *   The configuration array or NULL.
   */
  private function getSlicesConfig(): ?array {
    $slices = [];
    foreach ($this->config['dataset_form']['datasets']['slices'] ?? [] as $slice) {
      if (!is_array($slice) || !array_key_exists('metric', $slice) || $slice['metric'] == self::NONE) {
        continue;
      }
      $slices[] = $slice;
    }
    return $slices ?? NULL;
  }

  /**
   * Get the metric index for the full pie.
   *
   * @return int|null
   *   The index or NULL.
   */
  public function getFullPieIndex(): ?int {
    $index = $this->getFullPieConfig()['metric'] ?? NULL;
    return $index !== NULL ? (int) $index : NULL;
  }

  /**
   * Get the metric index for the polygon.
   *
   * @return int|null
   *   The index or NULL.
   */
  public function getPolygonIndex(): ?int {
    $index = $this->config['dataset_form']['datasets']['polygon']['metric'] ?? self::NONE;
    return $index !== self::NONE ? (int) $index : NULL;
  }

  /**
   * Get the metric indexes for the slices.
   *
   * @return int[]
   *   An array of metric indexes.
   */
  public function getSliceIndexes(): array {
    return array_map(function (array $slice): int {
      return (int) $slice['metric'];
    }, $this->getSlicesConfig() ?? []);
  }

  /**
   * Value callback for the attachment column in the configuration container.
   *
   * @return string
   *   The attachment description or NULL.
   */
  public function getAttachmentSummary(): string {
    $attachments = $this->getAttachments();
    if (empty($attachments)) {
      return $this->t('Missing');
    }
    return implode(', ', array_map(function ($attachment) {
      return $attachment->getTitle();
    }, $attachments));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    if ($this->getFullPieIndex() === NULL) {
      $errors[] = $this->t('No base metric (full pie) selected. The map will not be displayed.');
    }
    return $errors;
  }

}
