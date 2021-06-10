<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Provides a configuration container element.
 *
 * @FormElement("configuration_container")
 */
class ConfigurationContainer extends FormElement {

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
      '#plan_context' => NULL,
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
    $action = array_pop($parents);

    $new_mode = NULL;

    switch ($action) {
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
        $index = array_pop($parents);

        // Set the index of the editable item.
        $form_state->set('edit_item', $index);

        // Switch to edit mode.
        $new_mode = 'edit_item';
        break;

      case 'submit_item':
        $mode = $form_state->get('mode');
        $values = $form_state->getValue($parents);

        if ($mode == 'add_item') {
          $items[] = [
            'item_type' => $values['item_type'],
            'config' => $values['plugin_config'],
          ];
        }
        elseif ($mode == 'edit_item') {
          $index = $form_state->get('edit_item');
          $items[$index]['config'] = $values['plugin_config'];
        }

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'save_order':
        $sorted_rows = $form_state->getValue(array_merge($parents, ['summary_table']));
        uksort($items, function ($a, $b) use ($sorted_rows) {
          return $sorted_rows[$a]['weight'] > $sorted_rows[$b]['weight'];
        });
        break;

      case 'remove':
        array_pop($parents);
        $index = array_pop($parents);

        // Remove the requested index from the items.
        unset($items[$index]);

        // Switch to list mode.
        $new_mode = 'list';
        break;

      case 'cancel':
        // Switch to list mode.
        $new_mode = 'list';
        $form_state->set('current_item_type', NULL);
        break;
    }

    // Update stored items.
    $form_state->set('items', array_values($items));
    $form_state->setTemporaryValue($element['#parents'], array_values($items));

    if ($new_mode) {
      // Update the mode.
      $form_state->set('mode', $new_mode);
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
    return array_filter($values, function ($item_key) {
      return is_int($item_key);
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * Process the usage year form element.
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
    ];
    foreach ($exclude_form_keys as $exclude_form_key) {
      $form_state->addCleanValueKey(array_merge($element['#parents'], [$exclude_form_key]));
    }

    // Get the current mode.
    $mode = $form_state->has('mode') ? $form_state->get('mode') : 'list';

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if (!$form_state->has('items')) {
      $items = self::cleanItemValues($element['#default_value']);
      $form_state->set('items', $items);
      $form_state->setValue($element['#parents'], $items);
    }

    if ($mode == 'list') {
      self::buildSummaryTable($element, $form_state);
    }

    if ($mode == 'select_item_type' || $mode == 'add_item') {
      self::buildItemConfig($element, $form_state);
    }

    if ($mode == 'edit_item') {
      self::buildItemConfig($element, $form_state, $form_state->get('edit_item'));
    }

    return $element;
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
    $table_header = array_merge(
      [
        'item_type' => t('Item type'),
      ],
      $element['#preview']['columns'],
      [
        'weight' => t('Weight'),
        'operations' => '',
      ]
    );
    $element['summary_table'] = [
      '#type' => 'table',
      '#header' => $table_header,
      '#empty' => t('No rows have been added yet'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#attributes' => ['class' => ['summary-table']],
    ];
    $element['summary_table'] += self::buildTableRows($element, $form_state);

    $element['add_new_item'] = [
      '#type' => 'submit',
      '#value' => t('Add new item'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $item_count = count(Element::children($element['summary_table']));
    if (!empty($element['#max_items']) && $element['#max_items'] <= $item_count) {
      $element['add_new_item']['#disabled'] = TRUE;
      $element['add_new_item']['#attributes']['title'] = t('The maximum number of %max_items has been reached.', [
        '%max_items' => $element['#max_items'],
      ]);
    }

    $element['save_order'] = [
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
   *
   * @return array
   *   An array with one item per table row.
   */
  public static function buildTableRows(array $element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $rows = [];
    $items = $form_state->has('items') ? $form_state->get('items') : [];
    if (!empty($items)) {
      foreach ($items as $key => $item) {
        $item_type = self::getItemTypeInstance($item, $element);
        $row = [
          '#attributes' => ['class' => ['draggable']],
          '#weight' => $key,
        ];
        $row['item_type'] = [
          '#markup' => $item_type->getPluginLabel(),
        ];
        foreach (array_keys($element['#preview']['columns']) as $column_key) {
          $row[$column_key] = [
            '#markup' => $item_type->get($column_key),
          ];
        }
        $row['weight'] = [
          '#type' => 'weight',
          '#title' => t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $key,
          // Classify the weight element for #tabledrag.
          '#attributes' => [
            'class' => [
              'table-sort-weight',
            ],
          ],
        ];
        $row['operations'] = [
          '#type' => 'container',
          'edit' => [
            '#type' => 'submit',
            '#value' => t('Edit'),
            '#name' => 'edit-' . $key,
            '#ajax' => [
              'event' => 'click',
              'callback' => [static::class, 'updateAjax'],
              'wrapper' => $wrapper_id,
            ],
          ],
          'remove' => [
            '#type' => 'submit',
            '#value' => t('Remove'),
            '#name' => 'remove-' . $key,
            '#ajax' => [
              'event' => 'click',
              'callback' => [static::class, 'updateAjax'],
              'wrapper' => $wrapper_id,
            ],
          ],
        ];
        $rows[$key] = $row;
      }
    }
    return $rows;
  }

  /**
   * Build the config form part for item configuration.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param int $index
   *   Optional index argument to specifiy an existing item to be configured.
   */
  public static function buildItemConfig(array &$element, FormStateInterface $form_state, $index = NULL) {
    $wrapper_id = self::getWrapperId($element);
    $item_type_options = self::getAvailablePluginTypes($element);

    $element['item_config'] = [
      '#type' => 'container',
    ];

    $triggering_element = $form_state->getTriggeringElement();
    $trigger_parents = $triggering_element ? $triggering_element['#parents'] : [];

    if ($index === NULL || $form_state->get('mode') == 'select_item_type') {
      $values = $form_state->getValue($element['#parents']);
      if (!empty($values) && array_key_exists('item_config', $values)) {
        $item = array_filter($values['item_config'], function ($key) {
          return in_array($key, ['item_type', 'plugin_config']);
        }, ARRAY_FILTER_USE_KEY);
      }
      else {
        $item = [
          'item_type' => NULL,
        ];
      }

      $element['item_config']['item_type'] = [
        '#type' => 'select',
        '#title' => t('Item type'),
        '#options' => $item_type_options,
        '#required' => empty($item['item_type']),
        '#default_value' => !empty($item['item_type']) ? $item['item_type'] : NULL,
        '#description' => t('Select the item type that you want to add.'),
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      if ($form_state->get('mode') == 'select_item_type') {
        $element['item_config']['cancel_item_type'] = [
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
      $item = $items[$index];
    }

    if (!empty($item['item_type'])) {
      $form_state->set('current_item_type', $item['item_type']);
    }

    if (!empty($item['item_type']) && empty($trigger_parents)) {
      $form_state->set('mode', $index ? 'edit_item' : 'add_item');
    }

    $item_type = self::getItemTypeInstance($item, $element);
    if ($item_type && $form_state->get('mode') != 'select_item_type') {
      if ($index === NULL) {
        $element['item_config']['item_type']['#type'] = 'hidden';
        $element['item_config']['item_type']['#value'] = $item_type->getPluginId();
        $element['item_config']['item_type']['#default_value'] = $item_type->getPluginId();

        $element['item_config']['change_item_type'] = [
          '#type' => 'submit',
          '#value' => t('Change item type'),
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
      $element['item_config']['submit_item'] = [
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#name' => 'item-config-submit',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $form_state->get('mode') == 'select_item_type',
      ];

      $element['item_config']['cancel'] = [
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
    if (empty($item_type) || !array_key_exists($item_type, $element['#allowed_item_types'])) {
      return NULL;
    }
    $configuration = $element['#allowed_item_types'][$item_type];
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
   * Get the available plugin types.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   A simple array suitful for select fields. Key is the machine name, value
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

}
