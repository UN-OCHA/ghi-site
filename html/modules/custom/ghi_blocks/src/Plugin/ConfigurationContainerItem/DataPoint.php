<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Element\DataPoint as ElementDataPoint;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Provides a data point item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'data_point',
  label: new TranslatableMarkup('Data point'),
  description: new TranslatableMarkup('This item displays a single metric or measurement item.'),
)]
class DataPoint extends ConfigurationContainerItemPluginBase {

  use DataPointConfigBackwardsCompatibilityTrait;
  use PlanQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $data_point = $this->getSubmittedValue($element, $form_state, 'data_point');

    // Move legacy labels into the data point and hide default label for
    // configuration items.
    if (!empty($element['label']['#default_value'])) {
      $data_point['label'] = $element['label']['#default_value'];
    }
    $element['label']['#access'] = FALSE;
    $element['label']['#default_value'] = '';
    $element['label']['#value'] = '';

    $attachment = $this->getContextValue('attachment');
    $plan_object = $this->getContextValue('plan_object');
    $configuration = $this->getPluginConfiguration();
    /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $configuration['attachment_prototype'];

    $element['data_point'] = [
      '#type' => 'data_point',
      '#element_context' => $this->getContext(),
      '#attachment' => $attachment,
      '#attachment_prototype' => $attachment_prototype,
      '#plan_object' => $plan_object,
      '#select_monitoring_period' => $configuration['select_monitoring_period'],
      '#default_value' => $data_point,
      '#weight' => 5,
    ] + ($configuration['presets'] ?? []);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLabel() {
    $conf = $this->getDataPointConfig();
    $metric_type = $conf['data_points'][0]['metric_type'] ?? NULL;
    if ($metric_type === NULL) {
      return NULL;
    }
    // Get the protoype, as that is where the labels come from.
    $attachment = $this->getContextValue('attachment');
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    if (!$attachment_prototype && $attachment instanceof Attachment) {
      $attachment_prototype = $attachment->getPrototype();
    }
    if (!$attachment_prototype instanceof AttachmentPrototype) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    return $attachment_prototype->getDefaultFieldLabel($metric_type, $plan_object?->getPlanLanguage());
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $conf = $this->getDataPointConfig();
    if ($conf && array_key_exists('label', $conf) && !empty($conf['label'])) {
      return trim($conf['label']);
    }
    return parent::getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    $conf = $this->getDataPointConfig();
    return $attachment && $conf && $this->hasRequiredMetricTypes($conf) ? $attachment->getValue($conf) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    $conf = $this->getDataPointConfig();
    if (!$attachment || !$conf) {
      return NULL;
    }
    $config = $this->getPluginConfiguration();
    $build = $attachment->formatValue($conf);
    $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'] ?? [], $attachment->getValueCacheTags());
    $metric_type = $conf['data_points'][0]['metric_type'] ?? NULL;
    if (is_string($metric_type) && !empty($config['disaggregation_modal']) && $this->canShowDisaggregatedData($attachment, $conf)) {
      $link_url = Url::fromRoute('ghi_plans.modal_content.dissaggregation', [
        'attachment' => $attachment->id(),
        'metric_type' => $metric_type,
        'reporting_period' => $build['#reporting_period'] ?: 'latest',
      ]);
      $link_url->setOptions([
        'attributes' => [
          'class' => ['use-ajax', 'disaggregation-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => '80%',
            'title' => (string) $this->getLabel(),
            'classes' => [
              'ui-dialog' => 'disaggregation-modal ghi-modal-dialog',
            ],
          ]),
          'rel' => 'nofollow',
        ],
      ]);
      $text = [
        '#theme' => 'hpc_icon',
        '#icon' => 'view_list',
        '#tag' => 'span',
      ];
      $link = Link::fromTextAndUrl($text, $link_url);
      $modal_link = [
        '#theme' => 'hpc_modal_link',
        '#link' => $link->toRenderable(),
        '#tooltip' => $this->t('Click to see disaggregated data for <em>@column_label</em>.', [
          '@column_label' => $this->getLabel(),
        ]),
      ];
      $build['tooltips']['#tooltips']['disaggregation'] = $modal_link;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTableCell() {
    $cell = parent::getTableCell();
    $attachment = $this->getAttachmentObject();
    $conf = $this->getDataPointConfig();
    if ($attachment && $conf) {
      $tooltip = $attachment->getTooltip($conf);
      $cell['export_commentary'] = $tooltip['monitoring_period']['#tooltip'] ?? NULL;
    }
    return $cell;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getAttachmentObject()?->getValueCacheTags() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSortableValue() {
    $value = $this->getValue();
    if ($this->getColumnType() == 'percentage') {
      return $value * 100;
    }
    return $value;
  }

  /**
   * Whether the given attachment can show disaggregated data.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment object.
   * @param array $conf
   *   The data point configuration.
   *
   * @return bool
   *   TRUE if the attachment can show disaggregated data, FALSE otherwise.
   */
  public function canShowDisaggregatedData(Attachment $attachment, array $conf) {
    // Check cheap local conditions before consulting the Fabric-backed
    // disaggregation availability cache.
    return $conf['processing'] == 'single' && $attachment->canHaveDisaggregatedData() && $this->getValue() && $attachment->hasDisaggregatedData();
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnType() {
    $conf = $this->getDataPointConfig();
    if (!$conf) {
      return NULL;
    }
    if ($conf['formatting'] == 'percent') {
      return 'percentage';
    }
    if ($conf['processing'] == 'calculated' && $conf['calculation'] == 'percentage') {
      return 'percentage';
    }
    return parent::getColumnType();
  }

  /**
   * Get the currently configured data point configuration.
   *
   * @return array|null
   *   An array containing the data point configuration or NULL if no
   *   configuration is set.
   */
  public function getDataPointConfig() {
    $conf = $this->get('data_point');
    if (!is_array($conf) || empty($conf)) {
      return NULL;
    }
    if (ElementDataPoint::WIDGET_SUPPORT === FALSE && is_array($conf)) {
      $conf['widget'] = 'none';
    }
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    $configuration = $this->getPluginConfiguration();
    $conf['decimal_format'] = $plan_object ? $plan_object->getDecimalFormat() : NULL;
    $conf = $conf + ($configuration['presets'] ?? []);

    /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $attachment_prototype */
    if ($attachment_prototype = $this->getContextValue('attachment_prototype')) {
      $this->updateDataPointConfiguration($conf, $attachment_prototype);
    }
    return $conf;
  }

  /**
   * Check whether the normalized data point config has the required metrics.
   *
   * @param array $conf
   *   The normalized data point configuration.
   *
   * @return bool
   *   TRUE if the config can produce a data value, FALSE otherwise.
   */
  private function hasRequiredMetricTypes(array $conf): bool {
    if (empty($conf['data_points'][0]['metric_type'])) {
      return FALSE;
    }
    if (($conf['processing'] ?? 'single') == 'calculated' && empty($conf['data_points'][1]['metric_type'])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();

    $data_point_conf = $this->getDataPointConfig();
    if (!$data_point_conf) {
      return $classes;
    }
    $widget = $data_point_conf['widget'] ?? NULL;
    if (!empty($widget) && $widget != 'none') {
      $classes[] = Html::getClass($this->getPluginId() . '--widget');
      $classes[] = Html::getClass($this->getPluginId() . '--widget-' . $data_point_conf['widget']);
    }
    else {
      $classes[] = Html::getClass($this->getPluginId() . '--formatting-' . $data_point_conf['formatting']);
    }
    if ($attachment = $this->getContextValue('attachment')) {
      $classes[] = 'attachment-' . $attachment->id();
    }
    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAttributes() {
    $attributes = parent::getDataAttributes();
    if ($attachment = $this->getAttachmentObject()) {
      $attributes['data-attachment-id'] = $attachment->id();
    }
    return $attributes;
  }

  /**
   * Get the attachment object for this item.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment|null
   *   The attachment object or NULL.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof Attachment ? $attachment : NULL;
  }

}
