<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Helpers\AttachmentMatcher;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Helpers\StringHelper;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an attachment data item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'attachment_data',
  label: new TranslatableMarkup('Attachment data'),
  description: new TranslatableMarkup('This item displays a single metric or measurement item from a selected attachment.'),
)]
class AttachmentData extends ConfigurationContainerItemPluginBase {

  use PlanFootnoteTrait;
  use AttachmentFilterTrait;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery
   */
  public $attachmentPrototypeQuery;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AttachmentData {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $instance->fabricQueryManager->createInstance('attachment');
    $instance->attachmentPrototypeQuery = $instance->fabricQueryManager->createInstance('attachment_prototype');
    $instance->currentUser = $container->get('current_user');
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
    $errors = [];
    if (!empty($attachment_select['attachment_id'])) {
      $attachment_id = is_array($attachment_select['attachment_id']) ? reset($attachment_select['attachment_id']) : $attachment_select['attachment_id'];
      $attachment = $this->attachmentQuery->getAttachment($attachment_id);
      assert($attachment === NULL || $attachment instanceof Attachment);
      $errors = $attachment ? $this->validateAttachment($attachment) : [];
      $attachment = $attachment && empty($errors) ? $attachment : NULL;
      $attachment_select['attachment_id'] = $attachment?->id();
    }

    if (!$attachment && $trigger == 'submit_attachment') {
      $element['#element_errors'] = [
        $this->t('There was a problem loading the selected attachment. If the problem persists, please contact an administrator.'),
      ];
      if (!empty($errors) && User::load($this->currentUser->id())?->hasRole('administrator')) {
        $element['#element_errors'][] = $this->t('The original error message was: <em>@errors</em>', [
          '@errors' => implode('', $errors),
        ]);
      }
    }

    // See if we are in attachment select mode (or in data point configuration
    // mode).
    $attachment_select_mode = empty($attachment) || $triggered_by_change_request;

    if (!$attachment_select_mode) {
      $element['attachment_summary'] = [
        '#markup' => Markup::create('<strong>' . $this->t('Selected attachment: %attachment', ['%attachment' => $attachment->getDescription()]) . '</strong>'),
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
      // Move legacy labels into the data point and hide default label for
      // configuration items.
      if (!empty($element['label']['#default_value'])) {
        $data_point['label'] = $element['label']['#default_value'];
      }
      $element['label']['#access'] = FALSE;
      $element['label']['#default_value'] = '';
      $element['label']['#value'] = '';
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
   * Get the data point configuration.
   *
   * @return array
   *   A configuration array.
   */
  private function getDataPointConfig() {
    $conf = $this->get('data_point');
    $attachment = $this->getAttachmentObject();
    $attachment?->handleKnownConfigIssues($conf);
    return $conf;
  }

  /**
   * Get a default label.
   *
   * @return string|null
   *   A default label or NULL.
   */
  public function getDefaultLabel() {
    $attachment = $this->getAttachmentObject();
    $conf = $this->getDataPointConfig();
    $metric_type = $conf ? $conf['data_points'][0]['metric_type'] : NULL;
    if (!$attachment || $metric_type === NULL) {
      return NULL;
    }
    return $attachment->getPrototype()->getDefaultFieldLabel($metric_type, $attachment->getPlanLanguage());
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $conf = $this->getDataPointConfig();
    if (array_key_exists('label', $conf) && !empty($conf['label'])) {
      return trim($conf['label']);
    }
    return parent::getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    return $attachment?->getValue($this->get(['data_point']));
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }
    $data_point = $this->getDataPointConfig();
    $build = $attachment->formatValue($data_point);

    // See what property to use for footnotes if any.
    $metric_type = $data_point['data_points'][0]['metric_type'];
    $property = $metric_type;
    if ($attachment->isCalculatedField($metric_type) && $source = $attachment->getSourceTypeForCalculatedField($metric_type)) {
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
   * {@inheritdoc}
   */
  public function getDataAttributes() {
    $attributes = parent::getDataAttributes();
    if ($attachment = $this->getAttachmentObject()) {
      $attributes['data-attachment-id'] = $attachment->id();
    }
    if ($data_point = $this->getDataPointConfig()) {
      $attributes['data-metric-type'] = $data_point['data_points'][0]['metric_type'];
    }
    return $attributes;
  }

  /**
   * Get the attachment object for this item.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment|null
   *   The attachment object.
   */
  private function getAttachmentObject($validate = TRUE): ?Attachment {
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
    if ($validate && !empty($this->validateAttachment($attachment))) {
      return NULL;
    }
    return $attachment;
  }

  /**
   * Validate the given attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment to validate.
   *
   * @return array
   *   An array with validation errors.
   */
  private function validateAttachment(Attachment $attachment): array {
    $errors = [];

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $this->getContextValue('plan_object');

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $this->getContextValue('base_object');

    if (!$plan) {
      $errors[] = (string) $this->t('No plan available');
    }
    elseif ($attachment->getPlanId() != $plan->getSourceId()) {
      $errors[] = (string) $this->t('Configured attachment is not available in the context of the current plan');
    }
    elseif ($base_object && !$attachment->belongsToBaseObject($base_object)) {
      $errors[] = (string) $this->t('Configured attachment is not available in the context of the current base object');
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    $attachment = $this->getAttachmentObject(FALSE);

    if (!$attachment) {
      $errors[] = (string) $this->t('No attachment configured');
    }

    if (!empty($errors)) {
      return $errors;
    }

    return $this->validateAttachment($attachment);
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigurationErrors() {
    // Get the configured attachment id by reference to update it.
    $attachment_id = &$this->config['attachment']['attachment_id'];

    // Original attachment is the attachment object with id $attachment_id.
    $original_attachment = $this->getAttachmentObject(FALSE);

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $this->getContextValue('plan_object');
    if (!$plan) {
      // Nothing we can do here if no data object is available.
      return;
    }
    if ($original_attachment && $plan && $original_attachment->getPlanId() != $plan->getSourceId()) {
      // Unset the configured selected attachment if the plan changed.
      $attachment_id = NULL;
    }

    if ($original_attachment) {
      // Let's see if we can find an alternative attachment.
      $attachments = $this->attachmentQuery->getAttachmentsForPlan($plan->getSourceId(), $this->getContextValue('base_object'), [
        'AttachmentType' => [
          'Caseload',
          'Indicator',
        ],
      ]);
      $filtered_attachments = AttachmentMatcher::matchAttachments($original_attachment, $attachments);

      // Use the default plan caseload if available.
      $caseload_id = $plan->getPlanCaseloadId();
      if ($caseload_id && $original_attachment->getAttachmentType() == 'caseload' && array_key_exists($caseload_id, $filtered_attachments)) {
        $attachment_id = $caseload_id;
      }
      elseif (count($filtered_attachments) == 1) {
        // If there is only a single caseload available after doing the
        // matching, we take it. If there are multiple, we will bail out on
        // purpose to prevent misconfigurations that might be hard to spot.
        $attachment_id = array_key_first($filtered_attachments);
      }

      if (!empty($attachment_id) && array_key_exists($attachment_id, $filtered_attachments)) {
        // Lets see if we can assure that the data points are properly
        // translated if needed.
        $new_attachment = $filtered_attachments[$attachment_id];
        $data_point_conf = &$this->config['data_point'];
        $data_points = &$data_point_conf['data_points'];
        $data_points[0]['index'] = AttachmentMatcher::matchDataPointOnAttachments($data_points[0]['index'], $original_attachment, $new_attachment);
        if ($data_point_conf['processing'] != 'single') {
          $data_points[1]['index'] = AttachmentMatcher::matchDataPointOnAttachments($data_points[1]['index'], $original_attachment, $new_attachment);
        }

        if ($plan && $original_attachment->getPlanId() != $plan->getSourceId()) {
          // Reset the monitoring periods to latest if the template is applied
          // in a new plan context, before any previously selected values won't
          // be valid anymore in the plan context.
          $data_points[0]['monitoring_period'] = 'latest';
          $data_points[1]['monitoring_period'] = 'latest';
        }
      }
    }
  }

}
