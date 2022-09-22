<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);
    $this->attachmentQuery = $endpoint_query_manager->createInstance('attachment_query');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $attachment = $this->getContextValue('attachment');
    $configuration = $this->getPluginConfiguration();

    $data_point = $this->getSubmittedValue($element, $form_state, 'data_point');

    $element['data_point'] = [
      '#type' => 'data_point',
      '#element_context' => $this->getContext(),
      '#attachment' => $attachment,
      '#attachment_prototype' => $configuration['attachment_prototype'],
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
      return $attachment->fields[$this->get('data_point')['data_points'][0]];
    }
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    $fields = array_merge($attachment_prototype->fields ?? []);
    return $fields[$this->get('data_point')['data_points'][0]];
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
    return $attachment ? DataPointHelper::formatValue($attachment, $attachment->data_point_conf) : NULL;
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
