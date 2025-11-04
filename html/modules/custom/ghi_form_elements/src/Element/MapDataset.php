<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Element\Table;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Provides a map dataset element.
 */
#[FormElement('map_dataset')]
class MapDataset extends FormElementBase {

  use AjaxElementTrait;
  use PlanReportingPeriodTrait;

  const NONE = -1;

  const LABEL_IN_USE = 'Already in use';
  const LABEL_EMPTY = 'Empty';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => [],
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processMapDataset'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderMapDataset'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#multiple' => FALSE,
      '#disabled' => FALSE,
      '#attachment_ids' => [],
      '#max_slices' => 3,
    ];
  }

  /**
   * Get the initial values for a dataset item.
   *
   * @param array $element
   *   The form element.
   * @param array $parents
   *   The parents relative to the dataset storage root.
   * @param bool $required
   *   Whether this is a required row, e.g. 'full pie'.
   *
   * @return array
   *   The default dataset item.
   */
  public static function getInitialDatasetItem(array $element, array $parents, bool $required = FALSE): array {
    $default_values = [
      'attachment' => reset($element['#attachment_ids']),
      'metric' => $required ? 0 : self::NONE,
      'settings' => [],
    ];
    $element_default = $element['#default_value'] ?? [];
    $item = (NestedArray::getValue($element_default, $parents) ?? []) + $default_values;
    return self::sanitizeDatasetItem($item);
  }

  /**
   * Get the initial dataset.
   *
   * @param array $element
   *   The element holding the dataset.
   *
   * @return array
   *   The initial dataset.
   */
  private static function getInitialDataset(array $element) {
    $full_pie = self::getInitialDatasetItem($element, ['full_pie'], TRUE);
    $polygon = self::getInitialDatasetItem($element, ['polygon']);

    $slices = [];
    // Make sure we have the right number of slices.
    for ($i = 0; $i < $element['#max_slices']; $i++) {
      $slices[$i] = self::getInitialDatasetItem($element, ['slices', $i]);
    }

    $dataset = [
      'full_pie' => $full_pie,
      'polygon' => $polygon,
      'slices' => $slices,
    ];
    return $dataset;
  }

  /**
   * Get the root element from the given form state.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array|null
   *   A form element array.
   */
  private static function getRootElement(array $element, FormStateInterface $form_state): ?array {
    $parents = $element['#array_parents'];
    $dataset_parents = $element['#dataset_parents'] ?? NULL;
    if (empty($dataset_parents)) {
      return NULL;
    }

    // Find the root element of this map_dataset element.
    $element_root_index = array_search(reset($dataset_parents), $parents);
    $form = $form_state->getCompleteForm();
    return NestedArray::getValue($form, array_slice($parents, 0, $element_root_index));
  }

  /**
   * Get the current dataset id.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The dataset id of available.
   */
  private static function getDatasetId(array $element, FormStateInterface $form_state) {
    if (array_key_exists('#dataset_id', $element)) {
      return $element['#dataset_id'];
    }
    $root_element = self::getRootElement($element, $form_state);
    return $root_element['#dataset_id'] ?? NULL;
  }

  /**
   * Store the given dataset for the element.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $dataset
   *   The dataset to store.
   */
  private static function setDataset(array $element, FormStateInterface $form_state, array $dataset): void {
    $dataset_id = self::getDatasetId($element, $form_state);
    $form_state->set(['datasets', $dataset_id], $dataset);
  }

  /**
   * Retrieve the dataset for the element.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private static function getDataset($element, $form_state): array {
    $dataset_id = self::getDatasetId($element, $form_state);
    return $form_state->get(['datasets', $dataset_id]) ?? [];
  }

  /**
   * Sanitize a dataset item.
   *
   * @param array $item
   *   The item to sanitize.
   *
   * @return array
   *   The sanitized item.
   */
  private static function sanitizeDatasetItem(array &$item): array {
    $item = array_intersect_key($item, array_flip(['attachment', 'metric', 'settings']));
    $item['metric'] = $item['metric'] ?? self::NONE;
    if ($item['metric'] == self::NONE) {
      $item['settings'] = [];
    }
    $item['settings'] = array_intersect_key($item['settings'] ?? [], array_flip(['label', 'monitoring_period']));
    return $item;
  }

  /**
   * Get the used fields from the dataset.
   *
   * @param array $dataset
   *   The dataset array.
   * @param int $attachment_id
   *   The attachment id to which the used fields should relate.
   *
   * @return array
   *   An array of used field keys.
   */
  private static function getUsedFieldsFromDataset(array $dataset, int $attachment_id): array {
    $used_fields = [];
    $items = array_merge([$dataset['full_pie']], [$dataset['polygon']], $dataset['slices']);
    foreach ($items as $item) {
      if (!isset($item['attachment']) || $item['attachment'] != $attachment_id) {
        continue;
      }
      if ((int) $item['metric'] == (int) self::NONE || $item['metric'] === NULL) {
        continue;
      }
      $used_fields[] = $item['metric'];
    }
    $used_fields = array_unique($used_fields);
    return array_map('intval', $used_fields);
  }

  /**
   * Process the entity select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processMapDataset(array &$element, FormStateInterface $form_state) {
    self::resetFormStorage($form_state);

    $max_slices = $element['#max_slices'];
    $dataset = self::getDataset($element, $form_state);

    if (!$form_state->has(['datasets', $element['#dataset_id']])) {
      $dataset = self::getInitialDataset($element, $form_state);
      self::setDataset($element, $form_state, $dataset);
    }

    if (!$form_state->has('field_context')) {
      $attachment_ids = $element['#attachment_ids'];
      foreach ($attachment_ids as $attachment_id) {
        /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface $attachment */
        $attachment = self::loadAttachment($attachment_id);
        if (!$attachment) {
          continue;
        }
        $attachment_prototype = $attachment->getPrototype();
        // Only use goal metrics and measurement metrics, but not the calculated
        // fields as these are not mappable.
        $fields = $attachment_prototype->getGoalMetricFields() + $attachment_prototype->getMeasurementMetricFields();
        // Add the empty fields.
        $empty_fields = array_filter(array_keys($fields), function ($metric_index) use ($attachment) {
          $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE);
          return empty($disaggregated_data[$metric_index]) || $attachment->metricItemIsEmpty($disaggregated_data[$metric_index]);
        }, ARRAY_FILTER_USE_KEY);
        $form_state->set(['field_context', $attachment_id], [
          'fields' => $fields,
          'used_fields' => [],
          'empty_fields' => $empty_fields,
        ]);
      }
    }

    // Set the used fields based on the current dataset.
    foreach ($element['#attachment_ids'] as $attachment_id) {
      // Identify the fields already selected.
      $form_state->set(['field_context', $attachment_id, 'used_fields'], self::getUsedFieldsFromDataset($dataset, $attachment_id));
    }

    $common_row_options = [
      'field_context' => $form_state->get(['field_context']),
    ];

    // Build a representation of the rows that we can pass on to
    // ::buildDatasetRow().
    $rows = [];
    $rows['full_pie'] = [
      'title' => (string) t('Full pie'),
      'required' => TRUE,
      'dataset' => $dataset['full_pie'],
      'parents' => ['full_pie'],
    ];
    $rows['polygon'] = [
      'title' => (string) t('Polygon'),
      'dataset' => $dataset['polygon'],
      'parents' => ['polygon'],
    ];
    $rows['slices'] = [];
    foreach ($dataset['slices'] as $i => $slice) {
      if ($i > 0 && (int) $dataset['slices'][$i - 1]['metric'] === self::NONE) {
        continue;
      }
      $rows['slices'][$i] = [
        'title' => (string) t('Slice #@index', [
          '@index' => $i + 1,
        ]),
        'dataset' => $slice,
        'disabled_metrics' => [],
        'parents' => ['slices', $i],
      ];
      // Disallow unsetting this slice if the next one is set.
      for ($j = $i + 1; $j < $max_slices; $j++) {
        if (!empty($dataset['slices'][$j]) && (int) $dataset['slices'][$j]['metric'] !== self::NONE) {
          $rows['slices'][$i]['disabled_metrics'][] = self::NONE;
        }
      }
    }

    $wrapper_id = self::getWrapperId($element);

    // Now build the config table.
    $element = [
      '#type' => 'table',
      '#header' => [
        (string) t('Dataset'),
        (string) t('Attachment'),
        (string) t('Metric'),
        (string) t('Settings'),
        '',
      ],
      '#attributes' => [
        'class' => ['map-dataset-config-table'],
      ],
      '#cell_wrapping' => FALSE,
      '#pre_render' => [
        [self::class, 'preRenderSliceRows'],
        [Table::class, 'preRenderTable'],
        [self::class, 'preRenderDatasetRows'],
      ],
      '#tree' => TRUE,
      '#parents' => $element['#parents'],
      '#array_parents' => $element['#array_parents'],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#wrapper_id' => $wrapper_id,
      '#attachment_ids' => $element['#attachment_ids'],
      '#attached' => ['library' => ['ghi_form_elements/map_dataset']],
      '#dataset_id' => $element['#dataset_id'],
    ];

    $element['full_pie'] = self::buildDatasetRow($element, $form_state, $rows['full_pie'] + $common_row_options);
    $element['polygon'] = self::buildDatasetRow($element, $form_state, $rows['polygon'] + $common_row_options);
    foreach ($rows['slices'] as $key => $row) {
      $element['slices'][$key] = self::buildDatasetRow($element, $form_state, $row + $common_row_options);
    }
    return $element;
  }

  /**
   * Build a dataset table row.
   *
   * @param array $element
   *   The form element of the root level element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $row
   *   The row definition.
   *
   * @return array
   *   A form array.
   */
  private static function buildDatasetRow($element, FormStateInterface $form_state, $row) {
    $wrapper_id = $element['#wrapper_id'];

    // Set the defaults.
    $row += [
      'required' => FALSE,
      'disabled_metrics' => [],
      'wrapper' => NULL,
    ];
    $label = $row['title'];
    $required = $row['required'];
    $attachment_id = (int) ($row['dataset']['attachment'] ?? reset($element['#attachment_ids']));
    $current_metric = (int) ($row['dataset']['metric'] ?? ($required ? 0 : self::NONE));
    $settings = $row['dataset']['settings'] ?? NULL;
    $parents = $row['parents'];
    $field_context = $row['field_context'];

    $attachment = !empty($attachment_id) ? self::loadAttachment($attachment_id) : NULL;
    $plan_id = $attachment?->getPlanId() ?? NULL;

    $settings_edit = $form_state->get('settings_edit') === implode('_', $parents);

    $base_select = [
      '#access' => !$settings_edit,
      '#disabled' => $settings_edit,
      '#dataset_parents' => $parents,
      '#dataset_id' => $element['#dataset_id'],
      '#executes_submit_callback' => TRUE,
      '#element_submit' => [[static::class, 'selectSubmit']],
      '#theme' => 'select__form_options_attributes',
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateDatasetRowAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      '#attributes' => [
        'class' => [
          'glb-form-element',
          'glb-form-element--type-select',
        ],
      ],
      '#gin_lb_form_element' => FALSE,
      '#gin_lb_form' => FALSE,
    ];

    $base_button = [
      '#element_submit' => [[static::class, 'buttonSubmit']],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateDatasetRowAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      '#dataset_parents' => $parents,
      '#dataset_id' => $element['#dataset_id'],
    ];

    $build = [
      '#attributes' => [
        'class' => ['dataset-config-row'],
      ],
    ];

    $build['dataset'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $label,
    ];

    $limit_element_submit = array_merge($element['#array_parents'], $parents);

    if (!$settings_edit) {
      $build['attachment'] = [
        '#type' => 'select',
        '#title' => (string) t('Attachment'),
        '#title_display' => 'invisible',
        '#description' => $attachment?->getDescription() ?? NULL,
        '#options' => self::getAttachmentOptions($element['#attachment_ids']),
        '#default_value' => $attachment_id,
        '#limit_element_submit' => [$limit_element_submit],
        '#wrapper_attributes' => [
          'class' => [
            'glb-form-item',
            'dataset-attachment',
          ],
        ],
      ] + $base_select;
      $build['metric'] = [
        '#type' => 'select',
        '#title' => (string) t('Metric'),
        '#title_display' => 'invisible',
        '#options' => self::getSelectOptions($attachment_id, $current_metric, $field_context, $required),
        '#default_value' => $current_metric,
        '#limit_element_submit' => [$limit_element_submit],
        '#options_attributes' => [
          self::LABEL_IN_USE => self::getOptionAttributes($attachment_id, $current_metric, $field_context, 'used_fields'),
          self::LABEL_EMPTY => self::getOptionAttributes($attachment_id, $current_metric, $field_context, 'empty_fields'),
        ],
        '#wrapper_attributes' => [
          'class' => [
            'glb-form-item',
            'dataset-metric',
          ],
        ],
      ] + $base_select;

      if (!empty($row['disabled_metrics'])) {
        foreach ($row['disabled_metrics'] as $metric) {
          $build['metric']['#options_attributes'][$metric] = ['disabled' => 'disabled'];
        }
      }

      $build['settings_summary'] = [
        '#wrapper_attributes' => [
          'class' => [
            'dataset-settings-summary',
          ],
        ],
      ];

      $build['settings'] = [
        '#type' => 'value',
        '#value' => $settings,
      ];

      $summary_items = [];
      if ($current_metric !== self::NONE) {
        $summary_items[] = (string) t('Label: @label', [
          '@label' => ($settings['label'] ?? NULL) ?: $attachment?->getFieldByIndex($current_metric)->name->en,
        ]);
      }

      if ($attachment?->isMeasurementIndex($current_metric)) {
        $period_id = $settings['monitoring_period'] ?? 'latest';
        $period = self::getPlanReportingPeriod($plan_id, $settings['monitoring_period'] ?? 'latest');
        $summary_items[] = (string) t('Monitoring period: @monitoring_period', [
          '@monitoring_period' => $period_id == 'latest' ? t('Latest published') : $period?->format('#@period_number: @date_range'),
        ]);
      }

      $summary = implode('<br />', $summary_items);
      if ($summary) {
        $build['settings_summary'] = [
          '#type' => '#container',
          '#wrapper_attributes' => [
            'class' => [
              'dataset-settings-summary',
            ],
          ],
          [
            '#markup' => Markup::create('<div class="summary">' . $summary . '</div>'),
          ],
        ];
      }

      $build['settings_edit'] = $base_button + [
        '#type' => 'image_button',
        '#name' => implode('_', array_merge($parents, ['settings_edit'])),
        '#op' => 'edit',
        '#src' => 'core/misc/icons/787878/cog.svg',
        '#attributes' => ['alt' => t('Edit')],
        '#gin_lb_form_element' => FALSE,
        '#gin_lb_form' => FALSE,
        '#access' => $current_metric !== self::NONE && !$settings_edit,
        '#disabled' => $settings_edit,
        '#dataset_id' => $element['#dataset_id'],
        '#wrapper_attributes' => [
          'class' => [
            'dataset-settings-edit',
          ],
        ],
        '#limit_validation_errors' => [],
        '#limit_element_submit' => [],
      ];
    }

    if ($settings_edit) {
      $build['attachment'] = [
        '#type' => 'value',
        '#value' => $attachment_id,
      ];
      $build['metric'] = [
        '#type' => 'value',
        '#value' => $current_metric,
      ];
      $build['settings'] = [
        '#type' => 'container',
        '#access' => $settings_edit,
        '#colspan' => 4,
        '#wrapper_attributes' => [
          'class' => ['dataset-settings-container'],
        ],
      ];
      $build['settings']['context_summary'] = [
        '#type' => 'markup',
        '#markup' => new TranslatableMarkup('<strong>@attachment</strong>: @metric', [
          '@attachment' => $attachment?->getDescription() ?? t('Unknown attachment'),
          '@metric' => $attachment?->getPrototype()->getFields()[$current_metric] ?? t('No metric selected'),
        ]),
      ];
      $build['settings']['label'] = [
        '#type' => 'textfield',
        '#title' => (string) t('Label'),
        '#default_value' => $settings['label'] ?? NULL,
        '#attributes' => [
          'placeholder' => $current_metric !== self::NONE ? (string) $attachment?->getFieldByIndex($current_metric)->name->en : '',
        ],
      ];
      $build['settings']['monitoring_period'] = [
        '#type' => 'monitoring_period',
        '#title' => t('Monitoring period'),
        '#default_value' => $settings['monitoring_period'] ?? 'latest',
        '#plan_id' => $plan_id,
        '#add_wrapper' => FALSE,
        '#access' => $attachment->isMeasurementIndex($current_metric),
      ];
      $build['settings']['settings_actions'] = [
        // Important not to use type 'actions' here, otherwise an additional
        // empty button pane will be rendered in the modal.
        '#type' => 'container',
        'save_settings' => $base_button + [
          '#type' => 'submit',
          '#button_type' => 'primary',
          '#name' => implode('_', array_merge($parents, ['settings_update'])),
          '#value' => (string) t('Update'),
          '#op' => 'save',
          '#limit_element_submit' => [array_merge($limit_element_submit, ['settings'])],
        ],
        'cancel_settings' => $base_button + [
          '#type' => 'submit',
          '#name' => implode('_', array_merge($parents, ['settings_cancel'])),
          '#value' => (string) t('Cancel'),
          '#op' => 'cancel',
          '#limit_validation_errors' => [],
          '#limit_element_submit' => [],
        ],
      ];
    }
    return $build;
  }

  /**
   * Submit callback for select elements.
   */
  public static function selectSubmit(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || $triggering_element['#type'] != 'select') {
      return;
    }
    $parents = $element['#parents'];
    array_pop($parents);
    $dataset = self::getDataset($element, $form_state);
    $submitted = $form_state->getValue($parents);
    $submitted = self::sanitizeDatasetItem($submitted);
    NestedArray::setValue($dataset, $element['#dataset_parents'], $submitted, TRUE);
    self::setDataset($element, $form_state, $dataset);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for button elements.
   */
  public static function buttonSubmit($element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $allowed_types = ['submit', 'button', 'image_button'];
    if (!$triggering_element || !in_array($triggering_element['#type'], $allowed_types)) {
      return;
    }

    switch ($triggering_element['#op']) {
      case 'edit':
        // Store the row whose settings are currently being edited.
        $form_state->set('settings_edit', implode('_', $triggering_element['#dataset_parents']));
        break;

      case 'save':
        $form_state->set('settings_edit', NULL);
        $dataset = self::getDataset($element, $form_state) ?? [];
        // The parents point to the settings save button.
        $parents = $element['#parents'];
        // Remove 'settings[settings_actions][save_settings]'.
        array_pop($parents);
        array_pop($parents);
        array_pop($parents);
        // So we get the full submitted item to be able to sanitize it.
        $submitted = $form_state->getValue($parents);
        $submitted = self::sanitizeDatasetItem($submitted);

        // Build the dataset parents path.
        $dataset_parents = $element['#dataset_parents'];
        $dataset_parents[] = 'settings';

        // Update the dataset in the form state with the settingsd part of the
        // submitted values.
        NestedArray::setValue($dataset, $dataset_parents, $submitted['settings']);
        self::setDataset($element, $form_state, $dataset);
        break;

      case 'cancel':
        $parents = $triggering_element['#array_parents'];
        array_pop($parents);
        array_pop($parents);
        // Set the row back to 'non edit' mode.
        $form_state->set('settings_edit', NULL);
        // Discard any submitted values.
        $form_state->setValue($parents, []);
        break;
    }

    $form_state->setRebuild();
  }

  /**
   * Update a dataset row via ajax.
   */
  public static function updateDatasetRowAjax(&$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $dataset_parents = $triggering_element['#dataset_parents'];
    $parents = $triggering_element['#array_parents'];

    // Find the root element of this map_dataset element.
    $element_root_index = array_search(reset($dataset_parents), $parents);

    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $form_subset = NestedArray::getValue($form, array_slice($parents, 0, $element_root_index));

    $row = &NestedArray::getValue($form_subset, $dataset_parents);
    foreach (Element::children($row) as $column_key) {
      $row[$column_key]['#prefix'] = '<div class="ajax-new-content">';
      $row[$column_key]['#suffix'] = '<div/>';
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $form_subset));
    return $response;
  }

  /**
   * Check if there is a cancel action.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $cancel_key
   *   The string to look for when checking for a cancel action.
   *
   * @return bool
   *   TRUE if a cancel action, FALSE otherwise.
   */
  private static function isCancelAction(FormStateInterface $form_state, $cancel_key = 'cancel') {
    $trigger_parents = $form_state->getTriggeringElement()['#array_parents'] ?? [];
    return array_pop($trigger_parents) == $cancel_key;
  }

  /**
   * Reset the form storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  private static function resetFormStorage(FormStateInterface $form_state): void {
    if (self::isCancelAction($form_state)) {
      $storage = &$form_state->getStorage();
      unset($storage['field_context']);
      unset($storage['settings_edit']);
      unset($storage['datasets']);
    }
  }

  /**
   * Get the select options for the metric fields.
   *
   * @param int $attachment_id
   *   The id of the currently attachment.
   * @param int|string $current_metric
   *   The value of the currently selected metric.
   * @param int[] $field_context
   *   An field context.
   * @param bool $required
   *   Whether the select should be required.
   *
   * @return array
   *   An array of options.
   */
  private static function getSelectOptions(int $attachment_id, int|string $current_metric, array $field_context, $required = FALSE) {
    $options = [];
    if (!$required) {
      $options[self::NONE] = (string) t('None');
    }
    $fields = $field_context[$attachment_id]['fields'] ?? [];
    $used_fields = $field_context[$attachment_id]['used_fields'] ?? [];
    $empty_fields = $field_context[$attachment_id]['empty_fields'] ?? [];
    $used_fields = array_diff($used_fields, [$current_metric]);
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
   * @param int $attachment_id
   *   The id of the currently attachment.
   * @param int|string $current_metric
   *   The value of the currently selected metric.
   * @param int[] $field_context
   *   An field context.
   * @param string $type
   *   The type of field to set the attribute for. Either 'used_fields' or
   *   'empty_fields'.
   *
   * @return array
   *   An array of options attributes, keyed by the option value.
   */
  private static function getOptionAttributes(int $attachment_id, int|string $current_metric, array $field_context, string $type): array {
    $fields = $field_context[$attachment_id][$type] ?? [];
    return array_map(function (): array {
      return ['disabled' => 'disabled'];
    }, array_flip(array_diff($fields, [$current_metric])));
  }

  /**
   * Recursively set the element parents.
   *
   * @param array $element
   *   A form element array.
   */
  public static function setElementParents(array &$element) {
    foreach (Element::children($element) as $key) {
      $element[$key]['#parents'] = array_merge($element['#parents'], [$key]);
      $element[$key]['#array_parents'] = array_merge($element['#array_parents'], [$key]);
      self::setElementParents($element[$key]);
    }
  }

  /**
   * Prerender callback.
   */
  public static function preRenderMapDataset(array $element) {
    $element['#attributes']['type'] = 'map_dataset';
    self::setElementParents($element);
    Element::setAttributes($element, ['id', 'name', 'value']);

    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-map-dataset']);

    return $element;
  }

  /**
   * Pre-render the slice rows.
   *
   * @param array $element
   *   A form element.
   *
   * @return array
   *   A form element.
   */
  public static function preRenderSliceRows($element) {
    foreach (Element::children($element['slices']) as $key) {
      $element['slice_' . $key] = $element['slices'][$key];
    }
    unset($element['slices']);
    return $element;
  }

  /**
   * Pre-render the dataset rows.
   *
   * @param array $element
   *   A form element.
   *
   * @return array
   *   A form element.
   */
  public static function preRenderDatasetRows($element) {
    foreach (Element::children($element['#rows']) as $row_key) {
      $row = &$element['#rows'][$row_key];
      foreach ($row['data'] as $cell_key => &$cell) {
        if (($cell['data']['#type'] ?? 'markup') == 'value') {
          $element[$cell_key] = $row['data'][$cell_key];
          unset($row['data'][$cell_key]);
          continue;
        }
        if (!empty($cell['data']['#colspan'])) {
          $cell['colspan'] = $cell['data']['#colspan'];
        }
      }
    }
    return $element;
  }

  /**
   * Load an attachment object by its id.
   *
   * @param int $attachment_id
   *   The attachment id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface|null
   *   The attachment object or NULL.
   */
  public static function loadAttachment($attachment_id): DataAttachmentInterface|null {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query */
    $query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('attachment_query');
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface $attachment */
    $attachment = $query->getAttachment($attachment_id, TRUE);
    return $attachment instanceof DataAttachmentInterface ? $attachment : NULL;
  }

  /**
   * Get the attachment options for a select element.
   *
   * @param int[] $attachment_ids
   *   An array of attachment ids.
   *
   * @return string[]
   *   An array of attachment labels.
   */
  public static function getAttachmentOptions($attachment_ids): array {
    $attachments = [];
    foreach ($attachment_ids as $attachment_id) {
      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface $attachment */
      $attachment = self::loadAttachment($attachment_id);
      if (!$attachment) {
        continue;
      }
      $attachments[$attachment->id()] = $attachment;
    }
    ArrayHelper::sortObjectsByStringProperty($attachments, 'composed_reference');
    ArrayHelper::sortObjectsByStringProperty($attachments, 'sort_key');
    return array_map(function (DataAttachmentInterface $attachment) {
      return $attachment->getTitle();
    }, $attachments);
  }

}
