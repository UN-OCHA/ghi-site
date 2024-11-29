<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
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
      '#edit_label' => NULL,
      '#row_filter' => FALSE,
      '#parent_type_label' => NULL,
      '#groups' => FALSE,
    ];
  }

  /**
   * Get the parents array that is used for storing data for the given element.
   *
   * @param array $element
   *   A form element with a #parents key.
   *
   * @return array
   *   An array of parent keys.
   */
  public static function getStorageParents(array $element) {
    if (self::isInnerContainerElement($element)) {
      $parents = array_slice($element['#parents'], 0, array_search('custom_config', $element['#parents']) + 2);
      return $parents;
    }
    else {
      return $element['#parents'];
    }
  }

  /**
   * Get the parents array for the root parent of the given element.
   *
   * @param array $element
   *   A form element with a #parents key.
   *
   * @return array
   *   An array of parent keys for the root of the given element.
   */
  public static function getRootElementParents(array $element) {
    if (!self::isInnerContainerElement($element)) {
      return $element['#parents'];
    }
    return array_slice($element['#parents'], 0, array_search('custom_config', $element['#parents']));
  }

  /**
   * Wrapper around FormState::has().
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to retrieve.
   *
   * @return bool
   *   TRUE if the form storage for the given key exists, FALSE otherwise.
   */
  public static function has(array $element, FormStateInterface $form_state, $key) {
    return $form_state->has(array_merge(self::getStorageParents($element), [$key]));
  }

  /**
   * Wrapper around FormState::set().
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to set.
   * @param string $value
   *   The value to set.
   */
  public static function set(array $element, FormStateInterface $form_state, $key, $value) {
    $form_state->set(array_merge(self::getStorageParents($element), [$key]), $value);
  }

  /**
   * Wrapper around FormState::get().
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to retrieve.
   *
   * @return mixed
   *   The value for the given key on the given element.
   */
  public static function get(array $element, FormStateInterface $form_state, $key) {
    return $form_state->get(array_merge(self::getStorageParents($element), [$key]));
  }

  /**
   * Wrapper around FormState::has(), using the parent element.
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to retrieve.
   *
   * @return bool
   *   TRUE if the form storage for the given key exists, FALSE otherwise.
   */
  public static function parentHas(array $element, FormStateInterface $form_state, $key) {
    return $form_state->has(array_merge(self::getRootElementParents($element), [$key]));
  }

  /**
   * Wrapper around FormState::set() using the parent element.
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to set.
   * @param string $value
   *   The value to set.
   */
  public static function parentSet(array $element, FormStateInterface $form_state, $key, $value) {
    $root_parents = self::getRootElementParents($element);
    $form_state->set(array_merge($root_parents, [$key]), $value);
  }

  /**
   * Wrapper around FormState::get() using the parent element.
   *
   * This exists to assure a consistent storage.
   *
   * @param array $element
   *   A form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $key
   *   The key to retrieve.
   *
   * @return mixed
   *   The value for the given key on the given element.
   */
  public static function parentGet(array $element, FormStateInterface $form_state, $key) {
    return $form_state->get(array_merge(self::getRootElementParents($element), [$key]));
  }

  /**
   * Check if the given element is an inner container.
   *
   * @param array $element
   *   A form element array.
   *
   * @return bool
   *   TRUE if the given element is a configuration container element nested
   *   inside another configuration container element, FALSE otherwise.
   */
  public static function isInnerContainerElement(array $element) {
    return in_array('custom_config', $element['#parents']);
  }

  /**
   * Check if the given element represents an update to the outer container.
   *
   * @param array $element
   *   A form element array.
   *
   * @return bool
   *   TRUE if the given element represents an action that affects the outer
   *   container element, FALSE otherwise.
   */
  public static function isOuterContainerUpdate(array $element) {
    return in_array('parent_actions', $element['#parents']) && in_array('custom_config', $element['#parents']);
  }

  /**
   * Set the stored items for the given element.
   *
   * @param array $element
   *   A form element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $items
   *   The array of items to set.
   */
  public static function setItems(array $element, FormStateInterface $form_state, $items) {
    ArrayHelper::sortArrayByNumericKey($items, 'weight', EndpointQuery::SORT_ASC);
    self::set($element, $form_state, 'items', $items);
    $form_state->setTemporaryValue($element['#parents'], $items);
    $form_state->setValue($element['#parents'], $items);

    if (self::isInnerContainerElement($element)) {
      // Also update the items stored on the parent.
      $parent_items = self::parentGet($element, $form_state, 'items');
      ArrayHelper::sortArrayByNumericKey($parent_items, 'weight', EndpointQuery::SORT_ASC);

      $id = self::parentGet($element, $form_state, 'edit_item');
      $item_key = self::getItemIndexById($parent_items, $id);

      $config_key_index = array_search('custom_config', $element['#parents']);
      $nested_parents = array_slice($element['#parents'], $config_key_index + 1);
      NestedArray::setValue($parent_items, array_merge([$item_key, 'config'], $nested_parents), $items);
      self::parentSet($element, $form_state, 'items', $parent_items);

      $root_parents = self::getRootElementParents($element);
      $form_state->setTemporaryValue($root_parents, $parent_items);
      $form_state->setValue($root_parents, $parent_items);
    }
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
    $items = (array) self::get($element, $form_state, 'items');
    $triggering_element = $form_state->getTriggeringElement();

    $parents = $triggering_element['#parents'];
    $action = (string) array_pop($parents);

    if (end($parents) == 'actions' || end($parents) == 'parent_actions') {
      // Remove the actions key from the parents.
      array_pop($parents);
    }

    if (empty($parents)) {
      return;
    }

    if (!empty($triggering_element['#custom_action'])) {
      array_pop($parents);
      $id = array_pop($parents);
      self::set($element, $form_state, 'mode', 'custom_action');
      self::set($element, $form_state, 'custom_action', $action);
      self::set($element, $form_state, 'edit_item', $id);
      return;
    }

    if (self::get($element, $form_state, 'mode') == 'custom_action' && !self::isOuterContainerUpdate($triggering_element)) {
      // Don't try to apply actions if this is just the wrapper around another
      // element.
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
        $new_mode = self::get($element, $form_state, 'current_item_type') ? 'edit_item' : 'list';
        self::set($element, $form_state, 'current_item_type', NULL);
        break;

      case 'edit':
        array_pop($parents);
        $id = array_pop($parents);

        // Set the index of the editable item.
        self::set($element, $form_state, 'edit_item', $id);

        // Switch to edit mode.
        $new_mode = 'edit_item';

        // Check if this is a group.
        $items = self::get($element, $form_state, 'items');
        $item = self::getItemById($items, $id);
        $item_type = self::getItemTypeInstance($item, $element);
        if ($item_type->isGroupItem()) {
          $new_mode = 'edit_group';
        }
        break;

      case 'submit_group':
        $mode = self::get($element, $form_state, 'mode');
        $values = $form_state->cleanValues()->getValue($parents);
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
          $id = self::get($element, $form_state, 'edit_item');
          $index = self::getItemIndexById($items, $id);
          $items[$index]['config'] = $values['plugin_config'] + $items[$index]['config'];
        }

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'submit_item':
        $mode = self::get($element, $form_state, 'mode');
        $id = self::get($element, $form_state, 'edit_item');
        $index = self::getItemIndexById($items, $id);

        $values = $form_state->getValue(array_merge($parents));
        $values = $values['item_config'] ?? $values;

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
          $id = $max_id + 1;
          $items[] = [
            'id' => $id,
            'item_type' => $values['item_type'],
            'config' => $values['plugin_config'],
            'weight' => 0,
            'pid' => $pid,
          ];
        }
        elseif ($mode == 'edit_item') {
          $items[$index]['config'] = ($values['plugin_config'] ?? []) + $items[$index]['config'];
        }
        elseif ($mode == 'edit_item_filter') {
          $items[$index]['config']['filter'] = $values['filter_config'];
        }
        elseif ($mode == 'custom_action') {
          $custom_action = self::get($element, $form_state, 'custom_action');
          $items[$index]['config'][$custom_action] = $values[$custom_action];
        }

        // Let the item type react to it's submitted values.
        if ($id && $item = self::getItemById($items, $id)) {
          $item_type = self::getItemTypeInstance($item, $element);
          $item_type->submitForm($values['plugin_config'] ?? [], $mode);
        }

        // Switch to list mode.
        $new_mode = 'list';
        self::set($element, $form_state, 'current_item_type', NULL);
        break;

      case 'remove_filter':
        $mode = self::get($element, $form_state, 'mode');
        if ($mode == 'edit_item_filter') {
          $index = self::get($element, $form_state, 'edit_item');
          $items[$index]['config']['filter'] = NULL;
        }
        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'edit_filter':
        array_pop($parents);
        $id = array_pop($parents);

        // Set the index of the editable item.
        self::set($element, $form_state, 'edit_item', $id);

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
    self::setItems($element, $form_state, $items);

    if ($new_mode) {
      // Update the mode.
      self::set($element, $form_state, 'mode', $new_mode);
    }

    if ($new_mode == 'list' && self::isOuterContainerUpdate($triggering_element)) {
      self::parentSet($element, $form_state, 'current_item_type', NULL);
      self::parentSet($element, $form_state, 'edit_item', NULL);
      self::parentSet($element, $form_state, 'custom_action', NULL);
      self::parentSet($element, $form_state, 'mode', NULL);
      // Also set this containers mode to NULL. List is the default anyway and
      // we use this to know if we should use defaults.
      self::set($element, $form_state, 'mode', NULL);
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
        return self::get($element, $form_state, 'items');
      }
    }
    elseif (empty($form_state->getTriggeringElement()['#parents']) && self::has($element, $form_state, 'items')) {
      return self::get($element, $form_state, 'items');
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
      'parent_actions',
      'custom_config',
    ];
    foreach ($exclude_form_keys as $exclude_form_key) {
      $form_state->addCleanValueKey(array_merge($element['#parents'], [$exclude_form_key]));
    }

    // Get the current mode.
    $mode = self::get($element, $form_state, 'mode') ?? NULL;

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if (!self::has($element, $form_state, 'items') || ($mode == NULL && self::isInnerContainerElement($element))) {
      self::setItems($element, $form_state, $element['#default_value'] ?? []);
    }

    $mode = $mode ?? 'list';
    if ($mode == 'list') {
      self::buildSummaryTable($element, $form_state);
    }

    if ($mode == 'add_group') {
      self::buildGroupConfig($element, $form_state);
    }
    if ($mode == 'edit_group') {
      self::buildGroupConfig($element, $form_state, self::get($element, $form_state, 'edit_item'));
    }

    if ($mode == 'select_item_type' || $mode == 'add_item') {
      self::buildItemConfig($element, $form_state);
    }

    if ($mode == 'edit_item') {
      self::buildItemConfig($element, $form_state, self::get($element, $form_state, 'edit_item'));
    }

    if ($mode == 'edit_item_filter') {
      self::buildItemFilterConfig($element, $form_state, self::get($element, $form_state, 'edit_item'));
    }

    if ($mode == 'custom_action') {
      self::buildItemCustomActionForm($element, $form_state, self::get($element, $form_state, 'edit_item'), self::get($element, $form_state, 'custom_action'));
    }

    // Make sure that configuration container elements nested inside other
    // configuration container elements (via the custom actions logic) do have
    // a special set of submit and cancel buttons.
    if (self::isInnerContainerElement($element)) {
      self::buildParentActions($element, $form_state);
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
      [
        'issues' => t('Issues'),
        'operations' => '',
      ]
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
    $items = self::get($element, $form_state, 'items') ?? [];
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
    self::set($element, $form_state, 'items', $sorted_list);

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
          'class' => array_filter([
            'draggable',
            $item_type->isGroupItem() ? 'tabledrag-root' : 'tabledrag-leaf',
          ]),
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
      $row['issues'] = [
        '#markup' => implode(', ', $item_type->getConfigurationErrors()),
      ];

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
      '#value' => $element['#edit_label'] ?? t('Edit'),
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
          '#disabled' => !$item_type->isValidAction($element_key),
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

    $items = self::get($element, $form_state, 'items');
    $item = self::getItemById($items, $id);
    if (!$item) {
      $item = ['item_type' => 'item_group'];
      $id = NULL;
    }
    self::set($element, $form_state, 'mode', $id !== NULL ? 'edit_group' : 'add_group');
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

    if (($id === NULL || self::get($element, $form_state, 'mode') == 'select_item_type') && count($item_type_options) == 1) {
      $item = [
        'item_type' => array_key_first($item_type_options),
      ];
      self::set($element, $form_state, 'mode', $id !== NULL ? 'edit_item' : 'add_item');
    }
    elseif ($id === NULL || self::get($element, $form_state, 'mode') == 'select_item_type') {
      $values = $form_state->getValue($element['#parents']);
      if (!empty($values) && array_key_exists('item_config', $values) && array_key_exists('item_type', $values['item_config'])) {
        $item = array_filter($values['item_config'], function ($key) {
          return in_array($key, ['item_type', 'plugin_config']);
        }, ARRAY_FILTER_USE_KEY);
      }
      else {
        $item = [
          'item_type' => self::get($element, $form_state, 'current_item_type') ?? NULL,
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
      if (self::get($element, $form_state, 'mode') == 'select_item_type') {
        $element['item_config']['actions']['submit_item_type'] = [
          '#type' => 'submit',
          '#value' => t('Select @item_type_label type', [
            '@item_type_label' => $element['#item_type_label'],
          ]),
          '#name' => 'submit-item-type',
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
      $items = self::get($element, $form_state, 'items');
      $item = self::getItemById($items, $id);
    }

    if (!empty($item['item_type'])) {
      self::set($element, $form_state, 'current_item_type', $item['item_type']);
    }

    if (!empty($item['item_type']) && empty($trigger_parents)) {
      self::set($element, $form_state, 'mode', $id !== NULL ? 'edit_item' : 'add_item');
    }

    $item_type = self::getItemTypeInstance($item, $element);
    if ($item_type && self::get($element, $form_state, 'mode') != 'select_item_type') {

      // Add a description if available.
      if (count($item_type_options) > 1 && $plugin_description = $item_type->getPluginDescription()) {
        $element['item_config']['description'] = [
          '#type' => 'item',
          '#title' => $item_type->getPluginLabel(),
          '#title_display' => 'hidden',
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
          $element['title_wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['item-type-title-wrapper'],
            ],
            '#weight' => -1,
            'title' => [
              '#markup' => Markup::create('<h3>' . t('Selected type: %type', ['%type' => $item_type->getPluginLabel()]) . '</h3>'),
            ],
            'change_item_type' => [
              '#type' => 'submit',
              '#value' => t('Change @item_type_label type', [
                '@item_type_label' => strtolower($element['#item_type_label']),
              ]),
              '#ajax' => [
                'event' => 'click',
                'callback' => [static::class, 'updateAjax'],
                'wrapper' => $wrapper_id,
              ],
            ],
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

    if ($item_type && self::get($element, $form_state, 'mode') != 'select_item_type') {
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
        '#disabled' => self::get($element, $form_state, 'mode') == 'select_item_type' || !$item_type->canAddNewItem(),
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

    $items = self::get($element, $form_state, 'items');
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
   * Build the custom action form part for item configuration.
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

    $items = self::get($element, $form_state, 'items');
    $item = self::getItemById($items, $id);
    $item_key = self::getItemIndexById($items, $id);
    $item_type = self::getItemTypeInstance($item, $element);

    $element['custom_config'] = [
      '#type' => 'container',
      'parent_actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'actions-wrapper',
            'parent-actions',
          ],
        ],
        '#parents' => array_merge($element['#parents'], [
          'custom_config',
          'parent_actions',
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'custom_config',
          'parent_actions',
        ]),
      ],
    ];

    $callback = StringHelper::makeCamelCase($custom_action, TRUE);
    if ($item_type && method_exists($item_type, $callback)) {
      $element['custom_config']['#attributes'] = [
        'class' => Html::getClass($custom_action),
      ];
      $element['custom_config'][$custom_action] = [
        '#type' => 'container',
        '#parents' => array_merge($element['#parents'], [
          'custom_config',
          $custom_action,
        ]),
        '#array_parents' => array_merge($element['#array_parents'], [
          'custom_config',
          $custom_action,
        ]),
      ];

      $config = $items[$item_key]['config'][$custom_action] ?? [];
      $subform_state = SubformState::createForSubform($element['custom_config'][$custom_action], $element, $form_state);
      $element['custom_config'][$custom_action] += $item_type->$callback($element['custom_config'][$custom_action], $subform_state, $config);
    }

    $element['custom_config']['parent_actions']['submit_item'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#name' => 'custom-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $element['custom_config']['parent_actions']['cancel'] = [
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
   * Build the parent actions for nested configuration container items.
   *
   * This is for configuration container items that are nested inside another
   * configuration container.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public static function buildParentActions(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $element['parent_actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'actions-wrapper',
          'parent-actions',
        ],
      ],
    ];

    $translate_args = [
      '@label' => strtolower($element['#parent_type_label'] ?? $element['#item_type_label']),
    ];
    $element['parent_actions']['submit_item'] = [
      '#type' => 'submit',
      '#value' => t('Update @label', $translate_args),
      '#name' => 'item-config-submit',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#disabled' => self::get($element, $form_state, 'mode') == 'select_item_type',
    ];

    $element['parent_actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'item-config-parent-cancel',
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
