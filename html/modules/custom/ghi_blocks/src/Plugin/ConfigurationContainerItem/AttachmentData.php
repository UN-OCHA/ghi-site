<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;

/**
 * Provides an attachment data item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "attachment_data",
 *   label = @Translation("Attachment data"),
 *   description = @Translation("This item displays a single metric or measurement item from a selected attachment."),
 * )
 */
class AttachmentData extends ConfigurationContainerItemPluginBase {

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

    $configuration = $this->getPluginConfiguration();

    $attachment_select = $this->getSubmittedValue($element, $form_state, 'attachment', $form_state->get('attachment'));
    $data_point = $this->getSubmittedValue($element, $form_state, 'data_point');

    $element['attachment'] = [
      '#type' => 'attachment_select',
      '#default_value' => $attachment_select,
      '#element_context' => $this->getContext(),
      '#weight' => 1,
    ];

    $element['submit_attachment'] = [
      '#type' => 'button',
      '#value' => $this->t('Use selected attachment'),
      '#name' => 'submit-attachment',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 2,
    ];

    $trigger = $form_state->getTriggeringElement() ? (string) end($form_state->getTriggeringElement()['#parents']) : NULL;
    $triggered_by_change_request = $trigger == 'change_attachment';

    $attachment = NULL;
    if (!empty($attachment_select['attachment_id'])) {
      $attachment = $this->attachmentQuery->getAttachment($attachment_select['attachment_id']);
    }

    $element['label']['#access'] = !empty($attachment) && !$triggered_by_change_request;
    if ($attachment) {
      $form_state->set('attachment', $attachment_select);
      $element['attachment']['#hidden'] = TRUE;

      $element['attachment_summary'] = [
        '#markup' => Markup::create('<h3>' . $this->t('Selected attachment: %attachment', ['%attachment' => $attachment->composed_reference]) . '</h3>'),
        '#weight' => -1,
      ];

      if (!$triggered_by_change_request) {
        $element['submit_attachment']['#attributes']['class'][] = 'visually-hidden';
      }

      $element['change_attachment'] = [
        '#type' => 'button',
        '#value' => $this->t('Change attachment'),
        '#name' => 'change-attachment',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $this->wrapperId,
        ],
        '#weight' => 3,
      ];
      if ($triggered_by_change_request) {
        $element['change_attachment']['#disabled'] = TRUE;
        $element['change_attachment']['#attributes']['class'][] = 'visually-hidden';
      }

      $element['label']['#weight'] = 4;

      $element['data_point'] = [
        '#type' => 'data_point',
        '#element_context' => $this->getContext(),
        '#attachment' => $attachment,
        '#default_value' => $data_point,
        '#weight' => 5,
      ];
      if (array_key_exists('data_point', $configuration) && is_array($configuration['data_point'])) {
        foreach ($configuration['data_point'] as $config_key => $config_value) {
          $element['data_point']['#' . $config_key] = $config_value;
        }
      }
      if ($triggered_by_change_request) {
        $element['data_point']['#hidden'] = TRUE;
      }
    }

    return $element;
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
   * Get the attachment object for this item.
   */
  private function getAttachmentObject() {
    $attachment_id = $this->get(['attachment', 'attachment_id']);
    $data_point_conf = $this->get(['data_point']);
    if (!$attachment_id || !$data_point_conf) {
      return NULL;
    }
    $attachment = $this->attachmentQuery->getAttachment($attachment_id);
    if (!$attachment) {
      return NULL;
    }
    $attachment->data_point_conf = $data_point_conf;
    return $attachment;
  }

}
