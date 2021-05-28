<?php

namespace Drupal\ghi_configuration_container\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_configuration_container\ConfigurationContainerItemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration container element.
 *
 * @FormElement("configuration_container")
 */
class ConfigurationContainer extends FormElement implements ContainerFactoryPluginInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\ghi_configuration_container\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigurationContainerItemManager $configuration_container_item_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configurationContainerItemManager = $configuration_container_item_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.configuration_container_item_manager')
    );
  }

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
      '#theme_wrappers' => ['form_element'],
      '#max_items' => NULL,
      '#preview' => NULL,
      '#plan_context' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input && !empty($input['item_config'])) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    elseif (empty($form_state->getTriggeringElement()['#parents']) && $form_state->has('items')) {
      // This is the case on the final submissions of this form element. We
      // want to retain only the configured items, nothing more.
      $items = $form_state->get('items');
      $form_state->setValue($element['#parents'], $items);
      $form_state->addCleanValueKey(array_merge($element['#parents'], ['summary_table']));
      return $form_state->get('items');
    }
    if ($input) {
      return $input;
    }
    return NULL;
  }

  /**
   * Get a wrapper ID for this container element.
   *
   * @param array $element
   *   The form element.
   *
   * @return string
   *   The wrapper id.
   */
  public static function getWrapperId(array $element) {
    return implode('-', $element['#array_parents']) . '-hpc-configuration-container-wrapper';
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processConfigurationContainer(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);

    // Put the root path to this element into the form storage, to have it
    // easily available to update the full element after an ajax action.
    $form_state->set('element_parents', $element['#array_parents']);
    $mode = $form_state->has('mode') ? $form_state->get('mode') : 'list';

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if (!$form_state->has('items')) {
      $form_state->set('items', $element['#default_value']);
    }

    if ($mode == 'list') {
      self::buildSummaryTable($element, $form_state);
    }

    if ($mode == 'add_item') {
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
        // 'weight' => t('Weight'),
        'operations' => t('Operations'),
      ]
    );
    $element['summary_table'] = [
      '#type' => 'table',
      '#header' => $table_header,
      '#empty' => t('No rows have been added yet'),
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
      '#submit' => [
        [static::class, 'submitAddNewItem'],
      ],
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
        $item_type = self::getItemTypeInstance($item['item_type'], $element, $item['config']);
        $row = [
          // '#attributes' => ['class' => ['draggable']],
        ];
        $row['item_type'] = [
          '#type' => 'item',
          '#markup' => $item_type->getPluginLabel(),
        ];
        foreach (array_keys($element['#preview']['columns']) as $column_key) {
          $row[$column_key] = [
            '#type' => 'item',
            '#markup' => $item_type->get($column_key),
          ];
        }
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
            '#submit' => [
              [static::class, 'submitEditItem'],
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
            '#submit' => [
              [static::class, 'submitRemoveItem'],
            ],
          ],
        ];
        $rows[] = $row;
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

    if ($index === NULL) {
      $values = $form_state->getValue($element['#parents']);
      $item_config = !empty($values) && array_key_exists('item_config', $values) ? array_filter($values['item_config'], function ($key) {
        return in_array($key, ['item_type', 'plugin_config']);
      }, ARRAY_FILTER_USE_KEY) : NULL;
      $element['item_config']['item_type'] = [
        '#type' => 'select',
        '#title' => t('Add new item'),
        '#options' => array_merge([0 => 1], $item_type_options),
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    else {
      $items = $form_state->get('items');
      $item_config = $items[$index];
    }

    if (!empty($item_config['item_type']) && array_key_exists($item_config['item_type'], $item_type_options)) {
      $item_type = self::getItemTypeInstance($item_config['item_type'], $element, array_key_exists('config', $item_config) ? $item_config['config'] : []);
      $element['item_config']['plugin_config'] = [
        '#type' => 'container',
        '#parents' => array_merge($element['#parents'], [
          'item_config',
          'plugin_config',
        ]),
      ];
      $subform_state = SubformState::createForSubform($element['item_config']['plugin_config'], $element, $form_state);
      $element['item_config']['plugin_config'] += $item_type->buildForm($element['item_config']['plugin_config'], $subform_state);

      $element['item_config']['submit'] = [
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#name' => 'item_config_submit',
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#submit' => [
          [static::class, 'submitItem'],
        ],
      ];
    }
    $element['item_config']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#submit' => [
        [static::class, 'cancelItem'],
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
   * Generic ajax callback.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The part of the form structure that should be replaced.
   */
  public static function updateAjax(array &$form, FormStateInterface $form_state) {
    // Just update the full element.
    return NestedArray::getValue($form, $form_state->get('element_parents'));
  }

  /**
   * Submit handler for the add button.
   */
  public static function submitAddNewItem(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    $parents[] = 'element_select';

    // $selected_element = $form_state->getValue($parents);
    $form_state->set('mode', 'add_item');

    // Rebuild. Needed for processing to be called again.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the submit button of an item config.
   */
  public static function submitItem(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    $values = $form_state->getValue($parents);
    $mode = $form_state->get('mode');
    $items = (array) $form_state->get('items');

    // Handle new item vs edit item.
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

    // Update the stored items.
    $form_state->set('items', $items);

    // Go back to list mode.
    $form_state->set('mode', 'list');

    // Rebuild. Needed for processing to be called again.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the cancel button of an item config.
   */
  public static function submitEditItem(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    array_pop($parents);
    $index = array_pop($parents);

    // Set the index of the editable item.
    $form_state->set('edit_item', $index);

    // Switch to edit mode.
    $form_state->set('mode', 'edit_item');

    // Rebuild. Needed for processing to be called again.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the cancel button of an item config.
   */
  public static function submitRemoveItem(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    array_pop($parents);
    $index = array_pop($parents);
    $items = (array) $form_state->get('items');
    unset($items[$index]);
    $form_state->set('items', array_values($items));

    // Just go back to the list mode.
    $form_state->set('mode', 'list');

    // Rebuild. Needed for processing to be called again.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the cancel button of an item config.
   */
  public static function cancelItem(array &$form, FormStateInterface $form_state) {
    // Just go back to the list mode.
    $form_state->set('mode', 'list');

    // Rebuild. Needed for processing to be called again.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Get an instance for the given item type plugin.
   *
   * @param string $item_type
   *   The type of the plugin to instantiate.
   * @param array $element
   *   The form element.
   * @param array $instance_config
   *   Optional instance configuration.
   *
   * @return \Drupal\ghi_configuration_container\ConfigurationContainerItemPluginInterface
   *   An instantiated item plugin.
   */
  private static function getItemTypeInstance($item_type, array $element, array $instance_config = []) {
    if (empty($item_type) || !array_key_exists($item_type, $element['#allowed_item_types'])) {
      return NULL;
    }
    $configuration = $element['#allowed_item_types'][$item_type];
    $item_type = \Drupal::service('plugin.manager.configuration_container_item_manager')->createInstance($item_type, $configuration);
    if (!empty($instance_config)) {
      $item_type->setConfig($instance_config);
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
    $requested_item_types = array_keys($element['#allowed_item_types']);
    $definitions = \Drupal::service('plugin.manager.configuration_container_item_manager')->getDefinitions();
    foreach ($requested_item_types as $type_id) {
      if (!array_key_exists($type_id, $definitions)) {
        continue;
      }
      $available_types[$type_id] = $definitions[$type_id]['label'];
    }
    return $available_types;
  }

}
