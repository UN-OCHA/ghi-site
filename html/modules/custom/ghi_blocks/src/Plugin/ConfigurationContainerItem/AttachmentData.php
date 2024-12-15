<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_common\Helpers\StringHelper;
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
  use AttachmentFilterTrait;

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

    if (!$attachment && $trigger == 'submit_attachment') {
      $element['#element_errors'] = [
        $this->t('There was a problem loading the selected attachment. If the problem persists, please contact an administrator.'),
      ];
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
    if ($attachment->isCalculatedIndex($data_point_index) && $source = $attachment->getSourceTypeForCalculatedField($data_point_index)) {
      $property = StringHelper::camelCaseToUnderscoreCase($source);
    }

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $this->getContextValue('base_object');
    if ($property && $base_object instanceof Plan && $footnotes = $this->getFootnotesForPlanBaseobject($base_object)) {
      $build['tooltips']['#tooltips'][] = $this->buildFootnoteTooltip($footnotes, $property);
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

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    $attachment = $this->getAttachmentObject();

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $this->getContextValue('plan_object');
    if (!$attachment) {
      $errors[] = $this->t('No attachment configured');
    }
    elseif (!$plan) {
      $errors[] = $this->t('No plan available');
    }
    elseif ($attachment->getPlanId() != $plan->getSourceId()) {
      $errors[] = $this->t('Configured attachment is not available in the context of the current plan');
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigurationErrors() {
    $conf = &$this->config;
    $attachment_id = &$conf['attachment']['attachment_id'];

    $original_attachment = $this->getAttachmentObject();

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $this->getContextValue('plan_object');
    if ($original_attachment && $plan && $original_attachment->getPlanId() != $plan->getSourceId()) {
      $attachment_id = NULL;
    }

    if ($original_attachment) {
      // Let's see if we can find an alternative attachment.
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
      $query = $this->endpointQueryManager->createInstance('plan_entities_query');
      $query->setPlaceholder('plan_id', $plan->getSourceId());
      $attachments = $query->getDataAttachments($this->getContextValue('base_object'));
      $filtered_attachments = $this->matchDataAttachments($original_attachment, $attachments);

      // Use the default plan caseload if available.
      $caseload_id = $plan->getPlanCaseloadId();
      if ($caseload_id && $original_attachment->getType() == 'caseload' && array_key_exists($caseload_id, $filtered_attachments)) {
        $attachment_id = $caseload_id;
      }
      elseif (count($filtered_attachments) == 1) {
        $attachment_id = array_key_first($filtered_attachments);
      }
    }

    if (!empty($attachment_id)) {
      // Lets see if we can assure that the data points are properly translated
      // if needed.
      $new_attachment = $filtered_attachments[$attachment_id];
      $data_point_conf = &$this->config['data_point'];
      $data_points = &$data_point_conf['data_points'];
      $data_points[0]['index'] = $this->matchDataPointOnAttachments($data_points[0]['index'], $original_attachment, $new_attachment);
      if ($data_point_conf['processing'] != 'single') {
        $data_points[1]['index'] = $this->matchDataPointOnAttachments($data_points[1]['index'], $original_attachment, $new_attachment);
      }
    }
  }

  /**
   * Match a data point index on the given attachments.
   *
   * Matching is done by type, such that a data point of attachment 2 is
   * returned that has the same type as the given data point in attachment 1.
   *
   * @param int $data_point_index
   *   The data point index to match.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment_1
   *   The first or original attachment.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment_2
   *   The second or new attachment.
   *
   * @return int
   *   Either the original index if no match can be found or a new index.
   */
  private function matchDataPointOnAttachments($data_point_index, DataAttachment $attachment_1, DataAttachment $attachment_2) {
    // First get the original and the new fields. These are the types keyed by
    // the field index.
    $original_fields = $attachment_1->getPrototype()->getFieldTypes();
    $new_fields = $attachment_2->getPrototype()->getFieldTypes();
    if (!array_key_exists($data_point_index, $original_fields)) {
      // This is fishy.
      return $data_point_index;
    }

    // Compare the types.
    if ($original_fields[$data_point_index] == ($new_fields[$data_point_index] ?? NULL)) {
      // If they are the same, there is no need to go further.
      return $data_point_index;
    }
    // It's referring to a different type now, let's see if we can find the
    // same as the original type in the set of new fields.
    $new_index = array_search($original_fields[$data_point_index], $new_fields);

    // We either found a new index and can return it, or we didn't and we
    // return the original.
    return $new_index !== FALSE ? $new_index : $data_point_index;
  }

}
