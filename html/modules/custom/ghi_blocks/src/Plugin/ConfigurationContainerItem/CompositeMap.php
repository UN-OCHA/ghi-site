<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerItemCustomActionTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface;

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

  const MAX_SLICES = 3;

  const NONE = 'none';

  const LABEL_IN_USE = 'Already in use';
  const LABEL_EMPTY = 'Empty';

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'dataset_form' => $this->t('Datasets'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isValidAction($action) {
    return $this->getAttachment() instanceof DataAttachmentInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#required'] = TRUE;

    $attachments = $this->getContextAttachments();
    $attachment_options = array_map(function (DataAttachmentInterface $attachment) {
      return $attachment->getDescription();
    }, $attachments);

    $element['attachment'] = [
      '#type' => 'select',
      '#title' => $this->t('Attachment'),
      '#description' => $this->t('Select the attachment for this map. If there is an attachment missing, click on <em>Cancel</em> to go back to the main element configuration and then on <em>Attachments</em> to add it first.'),
      '#options' => $attachment_options,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
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
    self::setElementParents($element);
    $this->wrapperId = Html::getClass(implode('-', array_merge($element['#array_parents'])));
    $element['#prefix'] = '<div id="' . $this->wrapperId . '">';
    $element['#suffix'] = '</div>';
    $element['#attached']['library'][] = 'ghi_blocks/block_config.composite_map';

    $attachment = $this->getAttachment();
    $attachment_prototype = $attachment->getPrototype();

    // Only use goal metrics and measurement metrics, but not the calculated
    // fields as these are not mappable.
    $fields = $attachment_prototype->getGoalMetricFields() + $attachment_prototype->getMeasurementMetricFields();

    // Get the current or submitted values.
    $polygon = $this->getSubmittedValue($element, $form_state, ['dataset_form', 'polygon'], self::NONE);
    $full_pie = $this->getSubmittedValue($element, $form_state, ['dataset_form', 'full_pie'], 0);
    $slices = $this->getSubmittedValue($element, $form_state, ['dataset_form', 'slices'], []);

    // The submitted values above do not reflect the ajax submitted values. We
    // extract them from the raw user input but assure they are cast to int.
    $trigger_name = $form_state->getTriggeringElement()['#name'] ?? NULL;
    $user_input = $form_state->getUserInput();
    $user_input = NestedArray::getValue($user_input, $element['#parents']);
    if (!$trigger_name || str_starts_with($trigger_name, FormElementHelper::getStateSelector($element, []))) {
      $polygon = (int) $user_input['polygon'];
      $full_pie = (int) $user_input['full_pie'];
      $slices = array_filter(array_map(fn ($i) => $i !== self::NONE ? (int) $i : NULL, $user_input['slices']));
    }

    // Identify the fields already selected.
    $used_fields = array_unique(array_filter(array_merge([$polygon], [$full_pie], $slices), function ($value) {
      return $value !== self::NONE && $value !== NULL;
    }));
    $used_fields = array_map('intval', $used_fields);
    // And the empty fields.
    $empty_metrics = array_filter(array_keys($fields), function ($metric_index) use ($attachment) {
      $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE);
      return empty($disaggregated_data[$metric_index]) || $attachment->metricItemIsEmpty($disaggregated_data[$metric_index]);
    }, ARRAY_FILTER_USE_KEY);

    $ajax = [
      'event' => 'change',
      'callback' => [static::class, 'updateAjax'],
      'wrapper' => $this->wrapperId,
    ];

    $common_select_properties = [
      '#type' => 'select',
      '#theme' => 'select__form_options_attributes',
      '#ajax' => $ajax,
      '#attributes' => [
        'class' => [
          'glb-form-element',
          'glb-form-element--type-select',
        ],
      ],
      '#wrapper_attributes' => ['class' => ['glb-form-item']],
      '#gin_lb_form_element' => FALSE,
      '#gin_lb_form' => FALSE,
    ];

    $element['full_pie'] = [
      '#title' => (string) $this->t('Full pie'),
      '#description' => $this->t('Select the dataset to use for the full pie. This determines the size of the pie.'),
      '#options' => $this->getSelectOptions($fields, $used_fields, $empty_metrics, $full_pie, FALSE),
      '#default_value' => $full_pie,
      '#options_attributes' => [
        self::LABEL_IN_USE => $this->getOptionAttributes($used_fields, $full_pie),
        self::LABEL_EMPTY => $this->getOptionAttributes($empty_metrics, $full_pie),
      ],
      '#prefix' => '<div>',
    ] + $common_select_properties;

    $element['polygon'] = [
      '#title' => (string) $this->t('Polygon'),
      '#description' => $this->t('Select a dataset to use for the admin area background coloring.'),
      '#options' => $this->getSelectOptions($fields, $used_fields, $empty_metrics, $polygon),
      '#default_value' => $polygon,
      '#options_attributes' => [
        self::LABEL_IN_USE => $this->getOptionAttributes($used_fields, $polygon),
        self::LABEL_EMPTY => $this->getOptionAttributes($empty_metrics, $polygon),
      ],
      '#suffix' => '</div>',
    ] + $common_select_properties;

    $element['slices'] = [
      '#type' => 'container',
    ];
    for ($i = 0; $i < self::MAX_SLICES; $i++) {
      $state_selector = $i > 0 ? FormElementHelper::getStateSelector($element, ['slices', $i - 1]) : NULL;
      $element['slices'][$i] = [
        '#title' => (string) $this->t('Slice #@index', [
          '@index' => $i + 1,
        ]),
        '#description' => $this->t('Select an additional dataset to add as a slice to the donut. Each slice starts at 0 and they are overlayed on top of each other.'),
        '#options' => $this->getSelectOptions($fields, $used_fields, $empty_metrics, $slices[$i] ?? self::NONE),
        '#default_value' => $slices[$i] ?? self::NONE,
        '#states' => $state_selector ? [
          'visible' => [
            'select[name="' . $state_selector . '"]' => ['!value' => self::NONE],
          ],
        ] : NULL,
        '#options_attributes' => [
          self::LABEL_IN_USE => $this->getOptionAttributes($used_fields, $slices[$i] ?? self::NONE),
          self::LABEL_EMPTY => $this->getOptionAttributes($empty_metrics, $slices[$i] ?? self::NONE),
        ],
      ] + $common_select_properties;
      // Disallow unsetting this slice of any of the next ones is set.
      for ($j = $i + 1; $j < self::MAX_SLICES; $j++) {
        if (!empty($slices[$j]) && $slices[$j] !== self::NONE) {
          $element['slices'][$i]['#options_attributes'][self::NONE] = ['disabled' => 'disabled'];
        }
      }
    }

    return $element;
  }

  /**
   * Get the select options for the metric fields.
   *
   * @param string[] $fields
   *   An array of field labels, keyed by the index.
   * @param int[] $used_fields
   *   An array of indexes with used fields.
   * @param int[] $empty_fields
   *   An array of indexes with empty fields.
   * @param int|string $current
   *   The value of the currently selected field.
   * @param bool $optional
   *   Whether the select should be optional.
   *
   * @return array
   *   An array of options.
   */
  private function getSelectOptions(array $fields, array $used_fields, array $empty_fields, int|string $current, $optional = TRUE) {
    $options = [];
    if ($optional) {
      $options[self::NONE] = (string) $this->t('None');
    }
    $used_fields = array_diff($used_fields, [$current]);
    foreach ($fields as $key => $label) {
      if (in_array($key, $used_fields) || in_array($key, $empty_fields)) {
        continue;
      }
      $options[$key] = $label;
    }
    if (!empty($used_fields)) {
      $options[self::LABEL_IN_USE] = array_map(fn ($key) => $fields[$key], array_combine($used_fields, $used_fields));
    }
    if (!empty($empty_fields)) {
      $options[self::LABEL_EMPTY] = array_map(fn ($key) => $fields[$key], array_combine($empty_fields, $empty_fields));
    }
    return $options;
  }

  /**
   * Get the option attributes for the select options.
   *
   * @param array $used_fields
   *   An array of used fields in the form of int values.
   * @param int|string $current_field
   *   The currently selected field.
   *
   * @return array
   *   An array of options attributes, keyed by the option value.
   */
  private function getOptionAttributes(array $used_fields, int|string $current_field): array {
    return array_map(function () {
      return ['disabled' => 'disabled'];
    }, array_flip(array_diff($used_fields, [$current_field])));
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
   * Get the attachments from the context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface[]
   *   An array of attachment objects.
   */
  private function getContextAttachments(): array {
    $context = $this->getContext();
    /** @var int[] $attachment_ids */
    $attachment_ids = $context['attachment_ids'];
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_search_query');
    $attachments = $query->getAttachmentsById($attachment_ids);
    $attachments = array_filter($attachments, function (AttachmentInterface $attachment) {
      return $attachment instanceof DataAttachmentInterface;
    });
    return $attachments;
  }

  /**
   * Get the configured attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface
   *   The attachment object.
   */
  public function getAttachment(): DataAttachmentInterface|null {
    $attachments = $this->getContextAttachments();
    if (count($attachments) == 1) {
      return reset($attachments);
    }
    $attachment_id = $this->config['attachment'] ?? NULL;
    return $attachment_id ? ($attachments[$attachment_id] ?? NULL) : NULL;
  }

  /**
   * Get the metric index for the polygon.
   *
   * @return int|null
   *   The index or NULL.
   */
  public function getPolygonIndex(): int|null {
    $index = $this->config['dataset_form']['polygon'] ?? self::NONE;
    return $index !== self::NONE ? (int) $index : NULL;
  }

  /**
   * Get the metric index for the full pie.
   *
   * @return int|null
   *   The index or NULL.
   */
  public function getFullPieIndex(): int|null {
    $index = $this->config['dataset_form']['full_pie'] ?? NULL;
    return $index !== NULL ? (int) $index : NULL;
  }

  /**
   * Get the metric indexes for the slices.
   *
   * @return int[]
   *   An array of metric indexes.
   */
  public function getSliceIndexes(): array {
    $indexes = [];
    foreach ($this->config['dataset_form']['slices'] ?? [] as $slice) {
      if ($slice == self::NONE) {
        continue;
      }
      $indexes[] = (int) $slice;
    }
    return $indexes;
  }

  /**
   * Value callback for the attachment column in the configuration container.
   *
   * @return string
   *   The attachment description or NULL.
   */
  public function getAttachmentSummary(): string {
    return $this->getAttachment()?->getDescription();
  }

  /**
   * Value callback for the polygon column in the configuration container.
   *
   * @return string|null
   *   The label of the configured field or NULL.
   */
  public function getPolygonSummary(): string|null {
    return $this->getFieldLabel($this->getPolygonIndex());
  }

  /**
   * Value callback for the full pie column in the configuration container.
   *
   * @return string|null
   *   The label of the configured field or NULL.
   */
  public function getFullPieSummary(): string|null {
    return $this->getFieldLabel($this->getFullPieIndex());
  }

  /**
   * Value callback for the slices column in the configuration container.
   *
   * @return string|null
   *   The label of the configured field or NULL.
   */
  public function getSlicesSummary(): string|null {
    $labels = [];
    foreach ($this->getSliceIndexes() as $i => $slice) {
      $labels[] = '#' . $i + 1 . ': ' . $this->getFieldLabel($slice);
    }
    return !empty($labels) ? implode(', ', $labels) : NULL;
  }

  /**
   * Get the field label for the given metric index.
   *
   * @param int|string|null $index
   *   The metric item index as configured via ::buildForm().
   *
   * @return string|null
   *   The field label if available.
   */
  private function getFieldLabel($index): string|null {
    if ($index === NULL || $index == self::NONE) {
      return NULL;
    }
    $attachment = $this->getAttachment();
    $attachment_prototype = $attachment->getPrototype();
    return $attachment_prototype->getFields()[$index] ?? NULL;
  }

}
