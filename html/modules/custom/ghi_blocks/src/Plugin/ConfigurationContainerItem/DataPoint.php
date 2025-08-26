<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Element\DataPoint as ElementDataPoint;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a data point item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "data_point",
 *   label = @Translation("Data point"),
 *   description = @Translation("This item displays a single metric or measurement item."),
 * )
 */
class DataPoint extends ConfigurationContainerItemPluginBase {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $instance->endpointQueryManager->createInstance('attachment_query');
    return $instance;
  }

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
    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
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
    $data_point_conf = $this->getDataPointConfig();
    $data_point_index = $data_point_conf['data_points'][0]['index'] ?? NULL;
    if ($data_point_index === NULL) {
      return NULL;
    }
    // Get the protoype, as that is where the labels come from.
    $attachment = $this->getContextValue('attachment');
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    if (!$attachment_prototype && $attachment instanceof DataAttachment) {
      $attachment_prototype = $attachment->getPrototype();
    }
    if (!$attachment_prototype instanceof AttachmentPrototype) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    return $attachment_prototype->getDefaultFieldLabel($data_point_index, $plan_object?->getPlanLanguage());
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $data_point_conf = $this->get('data_point');
    if (array_key_exists('label', $data_point_conf) && !empty($data_point_conf['label'])) {
      return trim($data_point_conf['label']);
    }
    return parent::getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    $data_point_conf = $this->getDataPointConfig();
    return $attachment && $data_point_conf ? $attachment->getValue($data_point_conf) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    $data_point_conf = $this->getDataPointConfig();
    if (!$attachment || !$data_point_conf) {
      return NULL;
    }
    $config = $this->getPluginConfiguration();
    $build = $attachment->formatValue($data_point_conf);
    $data_point_index = $data_point_conf['data_points'][0]['index'] ?? NULL;
    if (is_int($data_point_index) && !empty($config['disaggregation_modal']) && $this->canShowDisaggregatedData($attachment, $data_point_conf)) {
      $link_url = Url::fromRoute('ghi_plans.modal_content.dissaggregation', [
        'attachment' => $attachment->id(),
        'metric' => $data_point_index,
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
    $data_point_conf = $this->getDataPointConfig();
    if ($attachment && $data_point_conf) {
      $tooltip = $attachment->getTooltip($data_point_conf);
      $cell['export_commentary'] = $tooltip['monitoring_period']['#tooltip'] ?? NULL;
    }
    return $cell;
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
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return bool
   *   TRUE if the attachment can show disaggregated data, FALSE otherwise.
   */
  public function canShowDisaggregatedData(DataAttachment $attachment, array $data_point_conf) {
    return $this->getValue() && $attachment->hasDisaggregatedData() && $data_point_conf['processing'] == 'single';
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnType() {
    $data_point_conf = $this->getDataPointConfig();
    if (!$data_point_conf) {
      return NULL;
    }
    if ($data_point_conf['formatting'] == 'percent') {
      return 'percentage';
    }
    if ($data_point_conf['processing'] == 'calculated' && $data_point_conf['calculation'] == 'percentage') {
      return 'percentage';
    }
    return parent::getColumnType();
  }

  /**
   * Get the currently configured data point configuration.
   *
   * @return array|null
   *   An array containing the data point configuration or null if no
   *   configuration is set.
   */
  public function getDataPointConfig() {
    $data_point_conf = $this->get('data_point');
    if (!is_array($data_point_conf) || empty($data_point_conf)) {
      return NULL;
    }
    if (ElementDataPoint::WIDGET_SUPPORT === FALSE && is_array($data_point_conf)) {
      $data_point_conf['widget'] = 'none';
    }
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    $configuration = $this->getPluginConfiguration();
    $data_point_conf['decimal_format'] = $plan_object ? $plan_object->getDecimalFormat() : NULL;
    $data_point_conf = $data_point_conf + ($configuration['presets'] ?? []);
    return $data_point_conf;
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
    return $classes;
  }

  /**
   * Get the attachment object for this item.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment|null
   *   The attachment object or NULL.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof DataAttachment ? $attachment : NULL;
  }

}
