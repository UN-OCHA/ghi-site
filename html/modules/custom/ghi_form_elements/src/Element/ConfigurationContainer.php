<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Provides a configuration container element.
 *
 * @FormElement("configuration_container")
 */
class ConfigurationContainer extends FormElement {

  use AjaxElementTrait {
    updateAjax as traitUpdateAjax;
  }
  use ConfigurationContainerGroup;

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
        [$class, 'processConfigurationContainer'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderConfigurationContainer'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#max_items' => NULL,
      '#preview' => NULL,
      '#element_context' => [],
      '#item_type_label' => $this->t('Item'),
      '#row_filter' => FALSE,
      '#item_type_label' => NULL,
      '#groups' => FALSE,
    ];
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

    $items = (array) $form_state->get('items');
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    $action = (string) array_pop($parents);

    if (end($parents) == 'actions') {
      // Remove the actions key from the parents.
      array_pop($parents);
    }

    if (!empty($triggering_element['#custom_action'])) {
      array_pop($parents);
      $id = array_pop($parents);
      $form_state->set('mode', 'custom_action');
      $form_state->set('custom_action', $action);
      $form_state->set('edit_item', $id);
      return;
    }

    $new_mode = NULL;

    switch ($action) {
      case 'add_group':
        $new_mode = 'add_group';
        break;

      case 'add_new_item':
      case 'change_item_type':
        $new_mode = 'select_item_type';
        break;

      case 'cancel_item_type':
        if ($form_state->get('current_item_type')) {
          $new_mode = 'edit_item';
        }
        else {
          $new_mode = 'list';
        }
        break;

      case 'edit':
        array_pop($parents);
        $id = array_pop($parents);

        // Set the index of the editable item.
        $form_state->set('edit_item', $id);

        // Switch to edit mode.
        $new_mode = 'edit_item';

        // Check if this is a group.
        $items = $form_state->get('items');
        $item = self::getItemById($items, $id);
        $item_type = self::getItemTypeInstance($item, $element);
        if ($item_type->isGroupItem()) {
          $new_mode = 'edit_group';
        }
        break;

      case 'submit_group':
        $mode = $form_state->get('mode');
        $values = $form_state->getValue($parents);
        if ($mode == 'add_group') {
          // Get the highest used id.
          $max_id = count($items) ? max(array_map(function ($_item) {
            return $_item['id'];
          }, $items)) : 0;
          $items[] = [
            'id' => $max_id + 1,
            'item_type' => 'item_group',
            'config' => $values['plugin_config'],
            'weight' => 0,
            'pid' => NULL,
          ];
        }
        elseif ($mode == 'edit_group') {
          $id = $form_state->get('edit_item');
          $index = self::getItemIndexById($items, $id);
          $items[$index]['config'] = $values['plugin_config'] + $items[$index]['config'];
        }

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'submit_item':
        $mode = $form_state->get('mode');
        $values = $form_state->getValue($parents);

        if ($mode == 'add_item') {
          $pid = NULL;
          if (self::canGroupItems($element) && $groups = self::getGroups($items)) {
            // See if we aready have groups. In that case we want to add new
            // items to the last group.
            $last_group = end($groups);
            $pid = $last_group['id'] ?? NULL;
          }
          // Get the highest used id.
          $max_id = count($items) ? max(array_map(function ($_item) {
            return $_item['id'];
          }, $items)) : 0;
          $items[] = [
            'id' => $max_id + 1,
            'item_type' => $values['item_type'],
            'config' => $values['plugin_config'],
            'weight' => 0,
            'pid' => $pid,
          ];
        }
        elseif ($mode == 'edit_item') {
          $id = $form_state->get('edit_item');
          $index = self::getItemIndexById($items, $id);
          $items[$index]['config'] = $values['plugin_config'] + $items[$index]['config'];
        }
        elseif ($mode == 'edit_item_filter') {
          $id = $form_state->get('edit_item');
          $index = self::getItemIndexById($items, $id);
          $items[$index]['config']['filter'] = $values['filter_config'];
        }
        elseif ($mode == 'custom_action') {
          $id = $form_state->get('edit_item');
          $custom_action = $form_state->get('custom_action');
          $index = self::getItemIndexById($items, $id);
          $items[$index]['config'][$custom_action] = $values[$custom_action];
        }

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'remove_filter':
        $mode = $form_state->get('mode');
        if ($mode == 'edit_item_filter') {
          $index = $form_state->get('edit_item');
          $items[$index]['config']['filter'] = NULL;
        }
        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'edit_filter':
        array_pop($parents);
        $id = array_pop($parents);

        // Set the index of the editable item.
        $form_state->set('edit_item', $id);

        // Switch to edit mode.
        $new_mode = 'edit_item_filter';
        break;

      case 'save_order':
        $sorted_rows = $form_state->getValue(array_merge($parents, ['summary_table']));
        foreach ($sorted_rows as $row) {
          $item_key = self::getItemIndexById($items, $row['id']);
          $pid = $row['pid'] ?? NULL;
          $items[$item_key]['weight'] = (int) $row['weight'];
          $items[$item_key]['pid'] = $pid !== '' && $pid !== NULL ? (int) $pid : NULL;
        }
        break;

      case 'remove':
        array_pop($parents);
        $id = array_pop($parents);
        $index = self::getItemIndexById($items, $id);
        // Remove the requested index from the items.
        unset($items[$index]);

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'cancel':
        // Switch to list mode.
        $new_mode = 'list';
        break;
    }

    // Update stored items.
    $form_state->set('items', $items);
    $form_state->setTemporaryValue($element['#parents'], $items);

    if ($new_mode) {
      // Update the mode.
      $form_state->set('mode', $new_mode);
    }
    if ($new_mode == 'list') {
      // Cleanup state.
      $form_state->set('current_item_type', NULL);
      $form_state->set('edit_item', NULL);
      $form_state->set('custom_action', NULL);
    }

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input && !empty($input['item_config'])) {
      // Make sure input is returned as normal during item configuration.
      $triggering_element = $form_state->getTriggeringElement();
      if (!$triggering_element || array_intersect($triggering_element['#parents'], $element['#parents'])) {
        return $input;
      }
      else {
        return $form_state->get('items');
      }
    }
    elseif (empty($form_state->getTriggeringElement()['#parents']) && $form_state->has('items')) {
      return $form_state->get('items');
    }
    if ($input) {
      return $input;
    }
    return NULL;
  }

  /**
   * Make sure that the given values array has only numeric indexes.
   *
   * @param array $values
   *   The input array.
   *
   * @return array
   *   The cleaned output array.
   */
  private static function cleanItemValues(array $values) {
    $values = array_filter($values, function ($item_key) {
      return is_int($item_key);
    }, ARRAY_FILTER_USE_KEY);
    $values = array_filter($values, function ($item) {
      return !empty($item['item_type']);
    });
    return $values;
  }

  /**
   * Process the configuration container form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processConfigurationContainer(array &$element, FormStateInterface $form_state) {
    $element['#attached']['library'][] = 'ghi_form_elements/configuration_container';

    $wrapper_id = self::getWrapperId($element);
    $exclude_form_keys = [
      'summary_table',
      'add_new_item',
      'save_order',
      'change_item_type',
      'actions',
    ];
    foreach ($exclude_form_keys as $exclude_form_key) {
      $form_state->addCleanValueKey(array_merge($element['#parents'], [$exclude_form_key]));
    }

    // Get the current mode.
    $mode = $form_state->has('mode') ? $form_state->get('mode') : 'list';

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if (!$form_state->has('items')) {
      $items = self::cleanItemValues((array) $element['#default_value']);
      $form_state->set('items', $items);
      $form_state->setValue($element['#parents'], $items);
    }

    if ($mode == 'list') {
      self::buildSummaryTable($element, $form_state);
    }

    if ($mode == 'add_group') {
      self::buildGroupConfig($element, $form_state);
    }
    if ($mode == 'edit_group') {
      self::buildGroupConfig($element, $form_state, $form_state->get('edit_item'));
    }

    if ($mode == 'select_item_type' || $mode == 'add_item') {
      self::buildItemConfig($element, $form_state);
    }

    if ($mode == 'edit_item') {
      self::buildItemConfig($element, $form_state, $form_state->get('edit_item'));
    }

    if ($mode == 'edit_item_filter') {
      self::buildItemFilterConfig($element, $form_state, $form_state->get('edit_item'));
    }

    if ($mode == 'custom_action') {
      self::buildItemCustomActionForm($element, $form_state, $form_state->get('edit_item'), $form_state->get('custom_action'));
    }

    unset($element['#description']);
    $element['#wrapper_attributes']['class'][] = Html::getClass($mode . '-view');
    return $element;
  }

  /**
   * See if this container can providing a grouping feature.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if this container can use groups, FALSE otherwise.
   */
  private static function canGroupItems(array $element) {
    $allowed_item_types = self::getAllowedItemTypes($element);
    return !empty($element['#groups']) && self::getGroupTypes($allowed_item_types) !== NULL;
  }

  /**
   * Build the summary table.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public static function buildSummaryTable(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $columns = $element['#preview']['columns'];
    if (self::elementSupportsFiltering($element)) {
      $columns['filter'] = t('Filter');
    }
    $item_type_options = self::getAvailablePluginTypes($element);
    $include_type_column = count($item_type_options) > 1;
    $table_header = array_merge(
      $include_type_column ? [
        'item_type' => t('@item_type_label type', [
          '@item_type_label' => $element['#item_type_label'],
        ]),
      ] : [],
      $columns,
      array_filter([
        'weight' => t('Weight'),
        'id' => [
          'data' => t('Id'),
          'class' => 'tabledrag-hide',
        ],
        'pid' => self::canGroupItems($element) ? t('Parent') : NULL,
      ]),
      ['operations' => '']
    );

    $table_rows = self::buildTableRows($element, $form_state, $include_type_column);
    $element['summary_table'] = [
      '#type' => 'table',
      '#caption' => $element['#description'] ?? NULL,
      '#header' => $table_header,
      '#empty' => t('Nothing has been added yet'),
      '#tabledrag' => array_filter([
        self::canGroupItems($element) ? [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'row-pid',
          'source' => 'row-id',
          'hidden' => TRUE,
          'limit' => FALSE,
        ] : NULL,
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ]),
      '#attributes' => [
        'class' => array_filter([
          'summary-table',
          empty($table_rows) ? 'empty-table' : NULL,
        ]),
      ],
    ];
    unset($element['#description']);
    $element['summary_table'] += $table_rows;

    $element['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
    ];

    if (self::canGroupItems($element)) {
      $element['actions']['add_group'] = [
        '#type' => 'submit',
        '#value' => t('Add new group'),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    $element['actions']['add_new_item'] = [
      '#type' => 'submit',
      '#value' => t('Add new @item_type_label', [
        '@item_type_label' => strtolower($element['#item_type_label']),
      ]),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#disabled' => self::canGroupItems($element) && empty($table_rows),
    ];

    $item_count = count(Element::children($element['summary_table']));
    if (!empty($element['#max_items']) && $element['#max_items'] <= $item_count) {
      $element['actions']['add_new_item']['#disabled'] = TRUE;
      $element['actions']['add_new_item']['#attributes']['title'] = t('The maximum number of %max_items has been reached.', [
        '%max_items' => $element['#max_items'],
      ]);
    }

    $element['actions']['save_order'] = [
      '#type' => 'submit',
      '#value' => t('Save order'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#access' => $item_count > 1,
    ];
  }

  /**
   * Build the table rows.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param bool $include_type_column
   *   Whether to include the plugin type column.
   *
   * @return array
   *   An array with one item per table row.
   */
  public static function buildTableRows(array $element, FormStateInterface $form_state, $include_type_column = TRUE) {
    $rows = [];
    $items = $form_state->has('items') ? $form_state->get('items') : [];
    if (empty($items)) {
      return $rows;
    }
    foreach ($items as $key => $item) {
      // Legacy check for the id property.
      if (!array_key_exists('id', $item)) {
        $items[$key]['id'] = $key;
      }
    }

    // Build the sorted list via a tree representation and update the items.
    $tree = self::buildTree($items);
    $sorted_list = self::buildFlatList($tree);
    $form_state->set('items', $sorted_list);

    foreach ($sorted_list as $key => $item) {
      $item += [
        'weight' => $key,
        'pid' => NULL,
      ];

      $item_type = self::getItemTypeInstance($item, $element);
      if (!$item_type) {
        continue;
      }

      $row = [
        '#attributes' => [
          'class' => [
            'draggable',
            $item_type->isGroupItem() ? 'tabledrag-root' : 'tabledrag-leaf',
          ],
        ],
        '#weight' => (int) $item['weight'],
      ];
      if ($include_type_column) {
        $row['item_type'] = [
          '#markup' => $item_type->getPluginLabel(),
        ];
      }
      foreach (array_keys($element['#preview']['columns']) as $column_key) {
        $preview = $item_type->preview($column_key);
        $row[$column_key] = is_array($preview) ? $preview : [
          '#markup' => $preview,
        ];
      }
      if (self::elementSupportsFiltering($element)) {
        $row['filter'] = [
          '#markup' => $item_type->getFilterSummary(),
        ];
      }
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $item['weight'],
        // Classify the weight element for #tabledrag.
        '#attributes' => [
          'class' => [
            'row-weight',
          ],
        ],
      ];
      $row['id'] = [
        '#type' => 'number',
        '#title' => t('Id'),
        '#title_display' => 'invisible',
        '#size' => 3,
        '#min' => 0,
        '#default_value' => $item['id'],
        '#disabled' => TRUE,
        // Classify the id element for #tabledrag.
        '#attributes' => [
          'class' => ['row-id', 'tabledrag-hide'],
        ],
        '#wrapper_attributes' => [
          'class' => ['tabledrag-hide'],
        ],
      ];
      if (self::canGroupItems($element)) {
        $row['pid'] = [
          '#type' => 'number',
          '#size' => 3,
          '#min' => 0,
          '#title' => t('Group'),
          '#title_display' => 'invisible',
          '#default_value' => $item['pid'],
          // Classify the pid element for #tabledrag.
          '#attributes' => [
            'class' => ['row-pid'],
          ],
        ];

        if ($row['pid']['#default_value'] !== NULL) {
          $indentation = [
            '#theme' => 'indentation',
            '#size' => 1,
          ];
          $column_keys = Element::children($row);
          $first_column_key = reset($column_keys);
          $row[$first_column_key]['#prefix'] = \Drupal::service('renderer')->render($indentation);
        }
      }
      $row['operations'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['operation-buttons'],
        ],
      ] + self::buildRowOperations($element, $item['id'], $item_type);
      $rows[$item['id']] = $row;
    }
    return $rows;
  }

  /**
   * Build the operation buttons for a row.
   *
   * @param array $element
   *   The form element.
   * @param int $key
   *   The numerical row key.
   * @param \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type
   *   The item type for a column.
   *
   * @return array
   *   An array of submit button form element arrays.
   */
  private static function buildRowOperations(array $element, $key, ConfigurationContainerItemPluginInterface $item_type) {
    $wrapper_id = self::getWrapperId($element);
    $operations = [];
    $operations['edit'] = [
      '#type' => 'submit',
      '#value' => t('Edit'),
      '#name' => 'edit-' . $key,
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    if (self::elementSupportsFiltering($element)) {
      $operations['edit_filter'] = [
        '#type' => 'submit',
        '#value' => t('Filter'),
        '#name' => 'filter-' . $key,
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    if ($item_type instanceof ConfigurationContainerItemCustomActionsInterface) {
      foreach ($item_type->getCustomActions() as $element_key => $label) {
        $operations[$element_key] = [
          '#type' => 'submit',
          '#value' => $label,
          '#name' => 'custom-action--' . $element_key . '--' . $key,
          '#custom_action' => $element_key,
          '#ajax' => [
            'event' => 'click',
            'callback' => [static::class, 'updateAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
      }
    }
    $operations['remove'] = [
      '#type' => 'submit',
      '#value' => t('Remove'),
      '#name' => 'remove-' . $key,
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    return $operations;
  }

  /**
   * Build the config form part for a group.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param int $id
   *   Optional id argument to specifiy an existing item to be configured.
   */
  public static function buildGroupConfig(array &$element, FormStateInterface $form_state, $id = NULL) {
    $wrapper_id = self::getWrapperId($element);

    $element['group_config'] = [
      '#type' => 'container',
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'actions-wrapper',
          ],
        ],
      ],
    ];

    $items = $form_state->get('items');
    $item = self::getItemById($items, $id);
    if (!$item) {
      $item = ['item_type' => 'item_group'];
      $id = NULL;
    }
    $form_state->set('mode', $id !== NULL ? 'edit_group' : 'add_group');
    $item_type = self::getItemTypeInstance($item, $element);

    $element['group_config']['plugin_config'] = [
      '#type' => 'container',
      '#parents' => array_merge($element['#parents'], [
        'group_config',
        'plugin_config',
      ]),
      '#array_parents' => array_merge($element['#array_parents'], [
        'group_config',
        'plugin_config',
      ]),
    ];
    $subform_state = SubformState::createForSubform($element['group_config']['plugin_config'], $element, $form_state);
    $element['group_config']['plugin_config'] += $item_type->buildForm($element['group_config']['plugin_config'], $subform_state);

    $element['group_config']['actions']['submit_group'] = [
      '#type' => 'submit',
      '#value' => $id !== NULL ? t('Update group') : t('Add group'),
      '#name' => 'group-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    $element['group_config']['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'group-config-cancel',
      '#limit_validation_errors' => [],
      // This is important to prevent form errors. Note that elementSubmit()
      // is still run for this button.
      '#submit' => [],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
  }

  /**
   * Build the config form part for item configuration.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param int $id
   *   Optional index argument to specifiy an existing item to be configured.
   */
  public static function buildItemConfig(array &$element, FormStateInterface $form_state, $id = NULL) {
    $wrapper_id = self::getWrapperId($element);
    $item_type_options = self::getAvailablePluginTypes($element);

    $element['item_config'] = [
      '#type' => 'container',
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'actions-wrapper',
          ],
        ],
        '#parents' => array_merge($element['#parents'], [
          'item_config',
          'actions',
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'item_config',
          'actions',
        ]),
      ],
    ];

    $triggering_element = $form_state->getTriggeringElement();
    $trigger_parents = $triggering_element ? $triggering_element['#parents'] : [];

    if (($id === NULL || $form_state->get('mode') == 'select_item_type') && count($item_type_options) == 1) {
      $item = [
        'item_type' => array_key_first($item_type_options),
      ];
      $form_state->set('mode', $id !== NULL ? 'edit_item' : 'add_item');
    }
    elseif ($id === NULL || $form_state->get('mode') == 'select_item_type') {
      $values = $form_state->getValue($element['#parents']);
      if (!empty($values) && array_key_exists('item_config', $values) && array_key_exists('item_type', $values['item_config'])) {
        $item = array_filter($values['item_config'], function ($key) {
          return in_array($key, ['item_type', 'plugin_config']);
        }, ARRAY_FILTER_USE_KEY);
      }
      else {
        $item = [
          'item_type' => $form_state->get('current_item_type') ?? NULL,
        ];
      }

      $element['item_config']['item_type'] = [
        '#type' => 'select',
        '#title' => t('@item_type_label type', [
          '@item_type_label' => $element['#item_type_label'],
        ]),
        '#options' => $item_type_options,
        '#required' => empty($item['item_type']),
        '#default_value' => !empty($item['item_type']) ? $item['item_type'] : NULL,
        '#description' => t('Select the @item_type_label type that you want to add.', [
          '@item_type_label' => strtolower($element['#item_type_label']),
        ]),
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      if ($form_state->get('mode') == 'select_item_type') {
        $element['item_config']['actions']['cancel_item_type'] = [
          '#type' => 'submit',
          '#value' => t('Cancel'),
          '#name' => 'cancel-item-type',
          '#limit_validation_errors' => [],
          // This is important to prevent form errors. Note that elementSubmit()
          // is still run for this button.
          '#submit' => [],
          '#ajax' => [
            'event' => 'click',
            'callback' => [static::class, 'updateAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
      }
    }
    else {
      $items = $form_state->get('items');
      $item = self::getItemById($items, $id);
    }

    if (!empty($item['item_type'])) {
      $form_state->set('current_item_type', $item['item_type']);
    }

    if (!empty($item['item_type']) && empty($trigger_parents)) {
      $form_state->set('mode', $id !== NULL ? 'edit_item' : 'add_item');
    }

    $item_type = self::getItemTypeInstance($item, $element);
    if ($item_type && $form_state->get('mode') != 'select_item_type') {

      // Add a description if available.
      if (count($item_type_options) > 1 && $plugin_description = $item_type->getPluginDescription()) {
        $element['item_config']['description'] = [
          '#type' => 'item',
          '#title' => $item_type->getPluginLabel(),
          '#markup' => $plugin_description,
          '#wrapper_attributes' => [
            'class' => ['plugin-description'],
          ],
        ];
      }

      if ($id === NULL) {
        $element['item_config']['item_type']['#type'] = 'hidden';
        $element['item_config']['item_type']['#value'] = $item_type->getPluginId();
        $element['item_config']['item_type']['#default_value'] = $item_type->getPluginId();

        // If there are more than one type, allow to change it when still
        // adding.
        if (count($item_type_options) > 1) {
          $element['item_config']['change_item_type'] = [
            '#type' => 'submit',
            '#value' => t('Change @item_type_label type', [
              '@item_type_label' => strtolower($element['#item_type_label']),
            ]),
            '#ajax' => [
              'event' => 'click',
              'callback' => [static::class, 'updateAjax'],
              'wrapper' => $wrapper_id,
            ],
          ];

          $element['title'] = [
            '#markup' => Markup::create('<h3>' . t('Selected type: %type', ['%type' => $item_type->getPluginLabel()]) . '</h3>'),
            '#weight' => -1,
          ];
        }
      }

      $element['item_config']['plugin_config'] = [
        '#type' => 'container',
        '#parents' => array_merge($element['#parents'], [
          'item_config',
          'plugin_config',
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'item_config',
          'plugin_config',
        ]),
      ];
      $subform_state = SubformState::createForSubform($element['item_config']['plugin_config'], $element, $form_state);
      $element['item_config']['plugin_config'] += $item_type->buildForm($element['item_config']['plugin_config'], $subform_state);
    }

    if ($item_type && $form_state->get('mode') != 'select_item_type') {
      $translate_args = [
        '@label' => strtolower($element['#item_type_label']),
      ];
      $element['item_config']['actions']['submit_item'] = [
        '#type' => 'submit',
        '#value' => $id === NULL ? t('Add @label', $translate_args) : t('Update @label', $translate_args),
        '#name' => 'item-config-submit',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $form_state->get('mode') == 'select_item_type',
      ];

      $element['item_config']['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => t('Cancel'),
        '#name' => 'item-config-cancel',
        '#limit_validation_errors' => [],
        // This is important to prevent form errors. Note that elementSubmit()
        // is still run for this button.
        '#submit' => [],
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
  }

  /**
   * Build the filter form part for item configuration.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param int $id
   *   Id to specifiy the item for which the filter is to be configured.
   */
  public static function buildItemFilterConfig(array &$element, FormStateInterface $form_state, $id) {
    $wrapper_id = self::getWrapperId($element);

    $items = $form_state->get('items');
    $item = self::getItemById($items, $id);
    $item_type = self::getItemTypeInstance($item, $element);

    $element['filter_config'] = [
      '#type' => 'container',
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'actions-wrapper',
          ],
        ],
        '#parents' => array_merge($element['#parents'], [
          'filter_config',
          'actions',
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'filter_config',
          'actions',
        ]),
      ],
    ];
    $element['filter_config']['element_description'] = [
      '#type' => 'item',
      '#title' => $item_type->getPluginLabel(),
      '#markup' => $item_type->getPluginDescription(),
    ];
    $element['filter_config']['filter_config'] = [
      '#type' => 'container',
      '#parents' => array_merge($element['#parents'], [
        'filter_config',
        'filter_config',
      ]),
      '#array_parents' => array_merge($element['#array_parents'], [
        'filter_config',
        'filter_config',
      ]),
    ];
    $subform_state = SubformState::createForSubform($element['filter_config']['filter_config'], $element, $form_state);
    $element['filter_config']['filter_config'] += $item_type->buildFilterForm($element['filter_config']['filter_config'], $subform_state);

    $element['filter_config']['actions']['submit_item'] = [
      '#type' => 'submit',
      '#value' => t('Save filter'),
      '#name' => 'filter-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $element['filter_config']['actions']['remove_filter'] = [
      '#type' => 'submit',
      '#value' => t('Delete filter'),
      '#name' => 'filter-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#disabled' => !$item_type->hasAppliccableFilter(),
    ];

    $element['filter_config']['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'filter-config-cancel',
      '#limit_validation_errors' => [],
      // This is important to prevent form errors. Note that elementSubmit()
      // is still run for this button.
      '#submit' => [],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
  }

  /**
   * Build the filter form part for item configuration.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param int $id
   *   Id to specifiy the item for which the filter is to be configured.
   * @param string $custom_action
   *   The custom action form to build.
   */
  public static function buildItemCustomActionForm(array &$element, FormStateInterface $form_state, $id, $custom_action) {
    $wrapper_id = self::getWrapperId($element);

    $items = $form_state->get('items');
    $item = self::getItemById($items, $id);
    $item_type = self::getItemTypeInstance($item, $element);

    $element['custom_config'] = [
      '#type' => 'container',
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'actions-wrapper',
          ],
        ],
        '#parents' => array_merge($element['#parents'], [
          'custom_config',
          'actions',
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'custom_config',
          'actions',
        ]),
      ],
    ];

    $callback = StringHelper::makeCamelCase($custom_action, FALSE);
    if (method_exists($item_type, $callback)) {
      $element['custom_config']['#attributes'] = [
        'class' => Html::getClass($custom_action),
      ];
      $element['custom_config'][$custom_action] = [
        '#parents' => array_merge($element['#parents'], [
          'custom_config',
          $custom_action,
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'custom_config',
          $custom_action,
        ]),
      ];
      $element['custom_config'][$custom_action] = $item_type->$callback($element['custom_config'][$custom_action], $form_state);
    }

    $element['custom_config']['actions']['submit_item'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#name' => 'custom-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $element['custom_config']['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'custom-config-cancel',
      '#limit_validation_errors' => [],
      // This is important to prevent form errors. Note that elementSubmit()
      // is still run for this button.
      '#submit' => [],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
  }

  /**
   * Prerender callback.
   */
  public static function preRenderConfigurationContainer(array $element) {
    $element['#attributes']['type'] = 'configuration_container';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-configuration-container']);
    return $element;
  }

  /**
   * Get the group types from the given item types.
   *
   * @param array $item_types
   *   An array of item type definitions.
   *
   * @return array|null
   *   An array of group item types definition or NULL if no group is found.
   */
  public static function getGroupTypes(array $item_types) {
    $groups = [];
    foreach ($item_types as $item_type => $configuration) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $instance */
      $instance = \Drupal::service('plugin.manager.configuration_container_item_manager')->createInstance($item_type, $configuration);
      if ($instance && $instance->isGroupItem()) {
        $groups[$item_type] = $instance;
      }
    }
    return !empty($groups) ? $groups : NULL;
  }

  /**
   * Get an instance for the given item type plugin.
   *
   * @param array $item
   *   The array describing the plugin to instantiate.
   * @param array $element
   *   The form element.
   *
   * @return \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface
   *   An instantiated item plugin.
   */
  private static function getItemTypeInstance(array $item, array $element) {
    $item_type = !empty($item['item_type']) ? $item['item_type'] : NULL;
    $allowed_item_types = self::getAllowedItemTypes($element);
    if (empty($item_type) || !array_key_exists($item_type, $allowed_item_types)) {
      return NULL;
    }
    $configuration = $allowed_item_types[$item_type];
    $item_type = \Drupal::service('plugin.manager.configuration_container_item_manager')->createInstance($item_type, $configuration);
    if (!empty($item['config'])) {
      $item_type->setConfig((array) $item['config']);
    }
    if (!empty($element['#element_context'])) {
      $item_type->setContext($element['#element_context']);
    }
    return $item_type;
  }

  /**
   * Get the allowed item types for this element.
   *
   * @param array $element
   *   The element definition.
   *
   * @return array
   *   An array of allowed item types.
   */
  private static function getAllowedItemTypes(array $element) {
    return $element['#allowed_item_types'];
  }

  /**
   * Get the available plugin types.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   A simple array suitable for select fields. Key is the machine name, value
   *   the label.
   */
  private static function getAvailablePluginTypes(array $element) {
    $available_types = [];
    $definitions = \Drupal::service('plugin.manager.configuration_container_item_manager')->getDefinitions();
    foreach ($element['#allowed_item_types'] as $type_id => $configuration) {
      if (!array_key_exists($type_id, $definitions)) {
        continue;
      }
      $definition = $definitions[$type_id];
      $instance = self::getItemTypeInstance(['item_type' => $type_id], $element);
      if (!$instance || $instance->isGroupItem()) {
        continue;
      }
      $callable = [$instance, 'access'];
      if (array_key_exists('access', $configuration) && !empty($configuration['access'] && is_callable($callable))) {
        if (!$instance->access($element['#element_context'], $configuration['access'])) {
          continue;
        }
      }
      $available_types[$type_id] = !empty($configuration['label']) ? $configuration['label'] : $definition['label'];
    }
    return $available_types;
  }

  /**
   * Whether this element supports filtering.
   *
   * @return bool
   *   TRUE if filtering is supported, FALSE otherwhise.
   */
  private static function elementSupportsFiltering($element) {
    return !empty($element['#row_filter']);
  }

  /**
   * Generic ajax callback.
   *
   * This is just a wrapper around AjaxElementTrait::updateAjax(), to be able
   * to deactivate the main submit button if a specific config item is
   * currently edited.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response with commands to update the relevant part of the form.
   */
  public static function updateAjax(array &$form, FormStateInterface $form_state) {
    $response = self::traitUpdateAjax($form, $form_state);
    $form_subset = NestedArray::getValue($form, self::$elementParentsFormKey);
    $submit_button_selector = '[data-drupal-selector="edit-actions-submit"]';
    $preview_selector = '[data-drupal-selector="edit-actions-subforms-preview"]';
    $item_config = array_key_exists('item_config', $form_subset);
    $filter_config = array_key_exists('filter_config', $form_subset);
    $custom_config = array_key_exists('custom_config', $form_subset);
    if ($item_config || $filter_config || $custom_config) {
      // Disable the main submit button on the block config form.
      $method = 'attr';
      $args = ['disabled', 'disabled'];

    }
    else {
      // Remove the disabled attribute again.
      $method = 'removeAttr';
      $args = ['disabled'];
    }
    $response->addCommand(new InvokeCommand($submit_button_selector, $method, $args));
    $response->addCommand(new InvokeCommand($preview_selector, $method, $args));
    return $response;
  }

}
