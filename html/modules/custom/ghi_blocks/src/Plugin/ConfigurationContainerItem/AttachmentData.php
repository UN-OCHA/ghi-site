<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Entity\Plan;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  use PlanFootnoteTrait;

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

    $configuration = $this->getPluginConfiguration();

    // Get the submitted values if any.
    $attachment_select = $this->getSubmittedValue($element, $form_state, 'attachment');
    $data_point = $this->getSubmittedValue($element, $form_state, 'data_point');

    // See what triggered the current form build.
    $trigger = $form_state->getTriggeringElement() ? (string) end($form_state->getTriggeringElement()['#parents']) : NULL;
    $triggered_by_change_request = $trigger == 'change_attachment';

    // Load an attachment if already selected.
    $attachment = NULL;
    if (!empty($attachment_select['attachment_id'])) {
      $attachment_id = is_array($attachment_select['attachment_id']) ? reset($attachment_select['attachment_id']) : $attachment_select['attachment_id'];
      $attachment = $this->attachmentQuery->getAttachment($attachment_id);
      $attachment_select['attachment_id'] = $attachment_id;
    }

    // See if we are in attachment select mode (or in data point configuration
    // mode).
    $attachment_select_mode = empty($attachment) || $triggered_by_change_request;

    if (!$attachment_select_mode) {
      $form_state->set('attachment', $attachment_select);
      $element['attachment_summary'] = [
        '#markup' => Markup::create('<strong>' . $this->t('Selected attachment: %attachment', ['%attachment' => $attachment->composed_reference]) . '</strong>'),
      ];
      $element['change_attachment'] = [
        '#type' => 'button',
        '#value' => $this->t('Change attachment'),
        '#name' => 'change-attachment',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $this->wrapperId,
        ],
        '#attributes' => [
          'class' => ['button-small'],
        ],
      ];
    }

    $element['attachment'] = [
      '#type' => 'attachment_select',
      '#default_value' => $attachment_select,
      '#element_context' => $this->getContext(),
      '#available_options' => [
        'entity_types' => TRUE,
        'attachment_prototypes' => TRUE,
      ],
      '#hidden' => !$attachment_select_mode,
    ];

    $element['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => array_filter([
          !$attachment_select_mode ? 'visually-hidden' : NULL,
          'second-level-actions-wrapper',
          'attachment-select-actions-wrapper',
        ]),
      ],
    ];

    // @todo We need a cancel option here too.
    $element['actions']['submit_attachment'] = [
      '#type' => 'button',
      '#value' => $this->t('Use selected attachment'),
      '#name' => 'submit-attachment',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#attributes' => [
        'class' => array_filter([!$attachment_select_mode ? 'visually-hidden' : NULL]),
      ],
    ];

    $element['label']['#access'] = !$attachment_select_mode;
    if ($attachment) {
      $element['label']['#weight'] = 4;
      $element['data_point'] = [
        '#type' => 'data_point',
        '#attachment' => $attachment,
        '#plan_object' => $this->getContextValue('plan_object'),
        '#default_value' => $data_point,
        '#weight' => 5,
        '#hidden' => $attachment_select_mode,
      ];
      if (array_key_exists('data_point', $configuration) && is_array($configuration['data_point'])) {
        foreach ($configuration['data_point'] as $config_key => $config_value) {
          $element['data_point']['#' . $config_key] = $config_value;
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    return $attachment ? $attachment->getValue($this->get(['data_point'])) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }

    $data_point_conf = $this->get(['data_point']);
    $build = $attachment->formatValue($data_point_conf);

    $data_point_index = $data_point_conf['data_points'][0]['index'];
    $property = $attachment->field_types[$data_point_index] ?? NULL;

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $this->getContextValue('base_object');
    if ($property && $base_object && $base_object instanceof Plan) {
      $footnotes = $this->getFootnotesForPlanBaseobject($base_object);
      $build[] = $this->buildFootnoteTooltip($footnotes, $property);
    }
    return $build;
  }

  /**
   * Get the attachment object for this item.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment|null
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment_id = $this->get(['attachment', 'attachment_id']);
    if (!$attachment_id) {
      return NULL;
    }
    // Cast this to a scalar if necessary.
    $attachment_id = is_array($attachment_id) ? array_key_first($attachment_id) : $attachment_id;
    $attachment = $this->attachmentQuery->getAttachment($attachment_id);
    if (!$attachment) {
      return NULL;
    }
    return $attachment;
  }

}
