<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an attachment data item for configuration containers.
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
    $attachment = $this->getContextValue('attachment');
    $plan_object = $this->getContextValue('plan_object');
    $configuration = $this->getPluginConfiguration();

    $data_point = $this->getSubmittedValue($element, $form_state, 'data_point');

    $element['data_point'] = [
      '#type' => 'data_point',
      '#element_context' => $this->getContext(),
      '#attachment' => $attachment,
      '#attachment_prototype' => $configuration['attachment_prototype'],
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
    $attachment = $this->getContextValue('attachment');
    if ($attachment) {
      return $attachment->fields[$this->get('data_point')['data_points'][0]['index']];
    }
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    $fields = array_merge($attachment_prototype->fields ?? []);
    return $fields[$this->get('data_point')['data_points'][0]['index']];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    return $attachment ? DataPointHelper::getValue($attachment, $attachment->data_point_conf) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }
    $config = $this->getPluginConfiguration();
    $build = DataPointHelper::formatValue($attachment, $attachment->data_point_conf);
    if (!empty($config['disaggregation_modal']) && $this->canShowDisaggregatedData($attachment)) {
      $data_point = $this->get('data_point')['data_points'][0]['index'];
      $link_url = Url::fromRoute('ghi_plans.modal_content.dissaggregation', [
        'attachment' => $attachment->id(),
        'metric' => $data_point,
        'reporting_period' => $attachment->getLatestPublishedReportingPeriod($attachment->getPlanId()) ?? 'latest',
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
      $build[] = $modal_link;
    }
    return $build;
  }

  /**
   * Whether the given attachment can show disaggregated data.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   *
   * @return bool
   *   TRUE if the attachment can show disaggregated data, FALSE otherwise.
   */
  public function canShowDisaggregatedData(DataAttachment $attachment) {
    return $this->getValue() && $attachment->hasDisaggregatedData() && $attachment->data_point_conf['processing'] == 'single';
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnType() {
    if ($this->get('data_point')['formatting'] == 'percent') {
      return 'percentage';
    }
    return parent::getColumnType();
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();

    $data_point_conf = $this->get('data_point');
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    $configuration = $this->getPluginConfiguration();
    $data_point_conf = $this->get('data_point');
    if (!$attachment || !$data_point_conf) {
      return NULL;
    }
    $data_point_conf['decimal_format'] = $plan_object ? $plan_object->getDecimalFormat() : NULL;
    $attachment->data_point_conf = $data_point_conf + ($configuration['presets'] ?? []);
    return $attachment;
  }

}
