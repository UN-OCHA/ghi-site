<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\node\NodeInterface;

/**
 * Provides a custom table rows element.
 *
 * @FormElement("custom_table_rows")
 */
class CustomTableRows extends FormElement {

  use AjaxElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processCustomTableRows'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCustomTableRows'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#columns' => [],
      '#column_tooltips' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return $form_state->has('rows') ? $form_state->get('rows') : [];
  }

  /**
   * Clean incoming rows from additional items accidentally added.
   */
  private static function cleanRows($rows) {
    return array_filter($rows, function ($row) {
      return is_array($row) && array_key_exists('plan_name', $row) && !empty($row['plan_name']);
    });
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
    $rows = $form_state->has('rows') ? $form_state->get('rows') : [];
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    $action = (string) array_pop($parents);
    array_pop($parents);

    switch ($action) {
      case 'add':
        $form_state->set('current_row', 'new');
        break;

      case 'edit':
        $index = array_pop($parents);
        $form_state->set('current_row', $index);
        break;

      case 'save':
        $values = $form_state->getValue($parents);
        $current_row = $form_state->get('current_row');
        $rows[$current_row] = array_diff_key($values, array_flip(['actions']));
        $form_state->set('rows', array_values($rows));
        $form_state->set('current_row', NULL);
        break;

      case 'cancel':
        $form_state->set('current_row', NULL);
        break;

      case 'save_order':
        // Get the new weights.
        $values = $form_state->getValue($parents);
        $weights = array_map(function ($item) {
          return $item['weight'];
        }, $values['rows'] ?? []);
        asort($weights);
        // Sort the stored rows according to the submitted weights.
        $rows = $form_state->get('rows');
        uksort($rows, function ($key1, $key2) use ($weights) {
          return $weights[$key1] - $weights[$key2];
        });
        // Update the stored rows.
        $form_state->set('rows', array_values($rows));

        break;

      case 'delete':
        $index = array_pop($parents);
        unset($rows[$index]);
        $form_state->set('rows', array_values($rows));
        break;

    }

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processCustomTableRows(array &$element, FormStateInterface $form_state) {
    $element['#attached']['library'] = ['ghi_form_elements/custom_table_rows'];
    $current_row = $form_state->get('current_row') ?? NULL;
    $edit_row = is_int($current_row) || $current_row === 'new';

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    if (!$form_state->has('rows')) {
      $table_rows = self::cleanRows($element['#default_value'] ?? []);
      $form_state->set('rows', $table_rows);
      $form_state->setValue($element['#parents'], $table_rows);
    }
    else {
      $table_rows = $form_state->get('rows');
    }
    $sortable = !$edit_row && !empty($table_rows);

    // Build the table header.
    $table_header = [];
    $table_header = ['columns' => 'Columns'];
    if ($sortable) {
      $table_header = array_merge($table_header, [
        'id' => [
          'data' => t('Id'),
          'class' => 'tabledrag-hide',
        ],
        'weight' => t('Weight'),
        'operations' => t('Operations'),
      ]);
    }

    $element['rows'] = [
      '#type' => 'table',
      '#caption' => $element['#description'] ?? NULL,
      '#header' => $table_header,
      '#empty' => t('Nothing has been added yet'),
      '#tabledrag' => $sortable ? [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ] : NULL,
      '#attributes' => [
        'class' => array_filter([
          'custom-table-rows-table',
          empty($table_rows) ? 'empty-table' : NULL,
        ]),
      ],
    ];

    if ($current_row === NULL && !empty($table_rows)) {
      foreach ($table_rows as $i => $table_row) {
        $element['rows'][$i] = self::buildTableRow($element, $form_state, $table_row, $i);
      }
    }
    elseif ($edit_row) {
      if (is_int($current_row)) {
        $row_index = $current_row;
        $element['rows'][$row_index] = self::buildTableRow($element, $form_state, $table_rows[$row_index], $row_index, TRUE);
      }
      elseif ($current_row === 'new') {
        $element['rows'][count($table_rows)] = self::buildTableRow($element, $form_state, NULL, 'new', TRUE);
      }
    }
    if (!$edit_row) {
      $element['actions']['add'] = [
        '#type' => 'submit',
        '#value' => t('Add new row'),
        '#name' => 'add',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $element['actions']['save_order'] = [
        '#type' => 'submit',
        '#value' => t('Save order'),
        '#name' => 'save_order',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    return $element;
  }

  /**
   * Build a single table row input.
   */
  private static function buildTableRow($element, $form_state, $row_data = NULL, $i = NULL, $edit = FALSE) {
    $columns = $element['#columns'];
    $wrapper_id = self::getWrapperId($element);

    $tooltip_map = !empty($element['#column_tooltips']) ? $element['#column_tooltips'] : [];

    $row = [
      '#attributes' => [
        'class' => !$edit ? [
          'draggable',
          'tabledrag-root',
        ] : [],
      ],
      '#weight' => !$edit ? $i : NULL,
    ];
    $row['columns'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($columns as $column_key => $column) {
      $default_value = $row_data === NULL ? NULL : ($row_data[$column_key] ?? '');
      if (is_array($column) && $column['#type'] == 'entity_autocomplete' && $default_value) {
        // Entity reference widget.
        if (array_key_exists(0, $default_value) && array_key_exists('target_id', $default_value[0])) {
          $node = \Drupal::entityTypeManager()->getStorage($column['#target_type'])->load($default_value[0]['target_id']);
          $default_value = $node ?? NULL;
        }
        else {
          $default_value = NULL;
        }
      }

      $column_title = is_array($column) ? $column['#title'] : $column;
      if (!empty($tooltip_map[$column_key])) {
        $column_title = new FormattableMarkup('<span style="position: relative;">@column_title@tooltip</span>', [
          '@column_title' => $column_title,
          '@tooltip' => Markup::create($tooltip_map[$column_key]),
        ]);
      }

      if (is_array($column)) {
        $row['columns'][$column_key] = $column;
      }
      else {
        $row['columns'][$column_key] = [
          '#type' => 'textfield',
          '#size' => $column_key == 'plan_name' ? 20 : 8,
        ];
      }
      $row['columns'][$column_key]['#title'] = $column_title;
      $row['columns'][$column_key]['#default_value'] = $default_value;
      if (!$edit) {
        $value = $row['columns'][$column_key]['#default_value'];
        $option_types = ['select', 'radios', 'checkboxes'];
        if (in_array($row['columns'][$column_key]['#type'], $option_types)) {
          $value = $row['columns'][$column_key]['#options'][$value];
        }
        if ($value instanceof NodeInterface) {
          $value = $value->label();
        }
        else {
          $value = (string) $value;
        }
        $row['columns'][$column_key] = [
          '#type' => 'item',
          '#title' => $row['columns'][$column_key]['#title'],
          '#markup' => strlen($value) == 0 ? t('-') : $value,
        ];
      }
      if ($edit) {
        // Operation column.
        $row['columns']['actions'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'custom-row-actions',
            ],
          ],
          '#weight' => 99,
        ];
        $row['columns']['actions']['save'] = [
          '#type' => 'submit',
          '#value' => t('Save'),
          '#name' => 'save_' . $i,
          '#ajax' => [
            'event' => 'click',
            'callback' => [static::class, 'updateAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
        $row['columns']['actions']['cancel'] = [
          '#type' => 'submit',
          '#value' => t('Cancel'),
          '#name' => 'cancel_' . $i,
          '#ajax' => [
            'event' => 'click',
            'callback' => [static::class, 'updateAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
      }
    }

    if (!$edit) {
      $row['id'] = [
        '#type' => 'number',
        '#title' => t('Id'),
        '#title_display' => 'invisible',
        '#size' => 3,
        '#min' => 0,
        '#default_value' => $edit ? NULL : $i,
        '#disabled' => TRUE,
        // Classify the id element for #tabledrag.
        '#attributes' => [
          'class' => ['row-id', 'tabledrag-hide'],
        ],
        '#wrapper_attributes' => [
          'class' => ['tabledrag-hide'],
        ],
      ];

      // Prepare for TableDrag support.
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for this row'),
        '#title_display' => 'invisible',
        '#value' => $edit ? NULL : $i,
        // Classify the weight element for #tabledrag.
        '#attributes' => [
          'class' => [
            'row-weight',
          ],
        ],
      ];
    }

    // Operation column.
    if (!$edit) {
      $row['actions'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'custom-row-actions',
          ],
        ],
      ];
      $row['actions']['edit'] = [
        '#type' => 'submit',
        '#value' => t('Edit'),
        '#name' => 'edit_' . $i,
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $row['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => t('Delete'),
        '#name' => 'delete_' . $i,
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    return $row;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderCustomTableRows(array $element) {
    $element['#attributes']['type'] = 'custom_table_rows';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-custom-table-rows']);
    return $element;
  }

}
