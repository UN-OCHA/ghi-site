<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\ghi_plans\Query\AttachmentQuery;

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
   * @var \Drupal\ghi_plans\Query\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AttachmentQuery $attachment_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->attachmentQuery = $attachment_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.attachment_query'),
    );
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
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLabel() {
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    return $attachment_prototype->fields[$this->get('data_point')['data_points'][0]];
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
  public function getClasses() {
    $classes = parent::getClasses();

    $data_point_conf = $this->get('data_point');
    if ($data_point_conf['widget'] != 'none') {
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
    $data_point_conf = $this->get('data_point');
    if (!$attachment || !$data_point_conf) {
      return NULL;
    }
    $attachment->data_point_conf = $data_point_conf;
    return $attachment;
  }

}
