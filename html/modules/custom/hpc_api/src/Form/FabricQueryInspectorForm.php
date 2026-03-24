<?php

namespace Drupal\hpc_api\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_api\Query\FabricQueryPluginInterface;

/**
 * Provides a form to execute arbitrary fabric queries.
 */
class FabricQueryInspectorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'fabric_query_inspector_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $plugin_options = $this->getPluginOptions();
    $plugin_id = $form_state->getValue('plugin_id') ?: array_key_first($plugin_options);
    $plugin = $this->getFabricQuery($plugin_id);

    $method_options = $this->getMethodOptions($plugin);
    $method_name = $form_state->getValue('method_name') ?? NULL;
    $method_name = ($method_name && !empty($method_options[$method_name])) ? $method_name : array_key_first($method_options);

    $wrapper_id = $this->getFormId() . '_wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['plugin_id'] = [
      '#type' => 'select',
      '#options' => $plugin_options,
      '#default_value' => $plugin->getPluginId(),
      '#title' => $this->t('Query plugin'),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $form['method_name'] = [
      '#type' => 'select',
      '#options' => $method_options,
      '#default_value' => $method_name,
      '#title' => $this->t('Method'),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $arguments = $plugin && $method_name ? $this->getArguments($plugin, $method_name) : [];
    $submitted_arguments = $form_state->getValue(['arguments', $method_name]);
    $form['arguments'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['arguments'][$method_name] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($arguments as $position => $argument) {
      $form['arguments'][$method_name][$position] = [
        '#type' => 'textfield',
        '#title' => ucfirst(str_replace('_', ' ', $argument['name'])),
        '#description' => implode(', ', array_filter([
          $argument['type'],
          $argument['required'] ? 'required' : 'optional',
          $argument['default_value'] ? ('default: ' . $argument['default_value']) : NULL,
        ])),
        '#default_value' => $submitted_arguments[$position] ?? NULL,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'style' => 'margin-bottom: 2rem;',
      ],
      '#disabled' => !($plugin && $method_name),
    ];

    if ($form_state->isSubmitted() && $plugin && $method_name) {
      $start = microtime(TRUE);
      $result = $this->callPluginMethod($plugin, $method_name, $submitted_arguments);
      $duration = microtime(TRUE) - $start;
      $form['meta'] = [
        '#type' => 'details',
        '#title' => $this->t('Meta'),
        '#open' => FALSE,
        'children' => [
          [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => $this->t('The method call took @duration seconds', [
              '@duration' => $duration,
            ]),
          ],
        ],
      ];
      $form['queries'] = [
        '#type' => 'container',
        '#title' => $this->t('Queries'),
        '#open' => FALSE,
        'children' => [
          [
            '#type' => 'table',
            '#header' => ['Query', 'Duration'],
            '#rows' => array_map(function ($query, $duration) {
              return [Markup::create('<pre>' . $query . '</pre>'), $duration];
            }, array_keys(QueryHelper::endpointCallTimeStorage()), QueryHelper::endpointCallTimeStorage()),
          ],
        ],
      ];

      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Result (processed)'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($error) ? print_r($this->castValue($result), TRUE) : print_r($error, TRUE),
        ],
      ];
      $form['result_json'] = [
        '#type' => 'details',
        '#title' => $this->t('Result (json)'),
        '#open' => FALSE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($error) ? json_encode($this->castValue($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : print_r($error, TRUE),
        ],
      ];
      $form['result_original'] = [
        '#type' => 'details',
        '#title' => $this->t('Result (original)'),
        '#open' => FALSE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($error) ? print_r($result, TRUE) : print_r($error, TRUE),
        ],
      ];
    }
    return $form;
  }

  /**
   * Generic ajax callback to be used by implementing classes.
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
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    $ajax = $triggering_element['#ajax'];

    if (!empty($ajax['wrapper'])) {
      $wrapper_id = $ajax['wrapper'];
      // Just update the full element.
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, !empty($parents) ? NestedArray::getValue($form, $parents) : $form));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $plugin_options = $this->getPluginOptions();
    $plugin_id = $form_state->getValue('plugin_id') ?: array_key_first($plugin_options);
    $plugin = $this->getFabricQuery($plugin_id);

    $method_options = $this->getMethodOptions($plugin);
    $method_name = $form_state->getValue('method_name') ?? NULL;
    $method_name = ($method_name && !empty($method_options[$method_name])) ? $method_name : array_key_first($method_options);

    $submitted_arguments = $form_state->getValue(['arguments', $method_name]);
    $arguments = $plugin && $method_name ? $this->getArguments($plugin, $method_name) : [];
    foreach ($arguments as $position => $argument) {
      if (!$argument['required']) {
        continue;
      }
      if (!empty($submitted_arguments[$position])) {
        continue;
      }
      if (empty($form['arguments'][$method_name])) {
        continue;
      }
      $form_state->setError($form['arguments'][$method_name][$position], $this->t('@field is required', [
        '@field' => $form['arguments'][$method_name][$position]['#title'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Get the query options.
   *
   * @return array
   *   Array of options, keys are the plugin ids, values are the labels.
   */
  private function getPluginOptions() {
    $definitions = $this->getFabricQueryDefinitions();
    $definitions = array_filter($definitions, fn ($definition) => !empty($this->getMethodsForClass($definition['class'])));
    return array_map(fn ($definition) => $definition['label'], $definitions);
  }

  /**
   * Get the available public methods for the given query plugin.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryPluginInterface $plugin
   *   The fabric query plugin.
   *
   * @return array
   *   Array of options, keys are the plugin ids, values are the labels.
   */
  private function getMethodOptions(FabricQueryPluginInterface $plugin) {
    return $this->getMethodsForClass(get_class($plugin));
  }

  /**
   * Get the available public methods for the given query plugin.
   *
   * @param string $class_name
   *   The class name.
   *
   * @return array
   *   Array of options, keys are the plugin ids, values are the labels.
   */
  private function getMethodsForClass(string $class_name) {
    $class = new \ReflectionClass($class_name);
    $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
    $options = [];
    foreach ($methods as $method) {
      if ($method->getDeclaringClass()->getNamespaceName() != $class->getNamespaceName()) {
        continue;
      }
      if (!$method->isPublic()) {
        continue;
      }
      $options[$method->getName()] = $method->getName();
    }
    return $options;
  }

  /**
   * Get the arguments for the query.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryPluginInterface $plugin
   *   The query plugin.
   * @param string $method_name
   *   The method name for which to get the arguments.
   *
   * @return array
   *   An array with the arguments that the method expects.
   */
  private function getArguments(FabricQueryPluginInterface $plugin, string $method_name): array {
    $class = new \ReflectionClass(get_class($plugin));
    $method = $class->getMethod($method_name);
    $allowed_types = ['int', 'string', 'array'];
    $options = [];
    foreach ($method->getParameters() as $arg) {
      if (!$arg->getType()) {
        continue;
      }
      $type = $arg->getType();
      if ($type instanceof \ReflectionUnionType && !empty(array_diff($type->getTypes(), $allowed_types))) {
        continue;
      }
      elseif ($type instanceof \ReflectionNamedType && !in_array($type->getName(), $allowed_types)) {
        continue;
      }
      $options[$arg->getPosition()] = [
        'position' => $arg->getPosition(),
        'name' => $arg->getName(),
        'type' => $type,
        'null' => $arg->allowsNull(),
        'required' => !$arg->isDefaultValueAvailable() && !$arg->allowsNull(),
        'default_value' => $arg->isOptional() ? $arg->getDefaultValue() : NULL,
      ];
    }
    return $options;
  }

  /**
   * Process argument values.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryPluginInterface $plugin
   *   The query plugin.
   * @param string $method_name
   *   The method name.
   * @param array $values
   *   The submitted values.
   */
  private function processArgumentValues(FabricQueryPluginInterface $plugin, string $method_name, array &$values) {
    $arguments = $this->getArguments($plugin, $method_name);
    foreach ($values as $position => &$value) {
      $type = $arguments[$position]['type'] ?? NULL;
      if (!$type) {
        continue;
      }
      if ($type instanceof \ReflectionUnionType && in_array('array', $type->getTypes()) || $type instanceof \ReflectionNamedType && $type->getName() == 'array') {
        if (str_contains($value, ',')) {
          $value = explode(',', $value);
        }
        else {
          $value = [$value];
        }
      }
      elseif ($type instanceof \ReflectionNamedType && $type->getName() == 'int') {
        $value = (int) $value;
      }
      else {
        $value = NULL;
      }
    }
  }

  /**
   * Call a method on the given plugin.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryPluginInterface $plugin
   *   The query plugin.
   * @param string $method_name
   *   The method name to call.
   * @param array|null $arguments
   *   The arguments for the method.
   *
   * @return mixed
   *   The return value of the method call.
   */
  private function callPluginMethod(FabricQueryPluginInterface $plugin, string $method_name, ?array $arguments = []) {
    $class = new \ReflectionClass(get_class($plugin));
    $method = $class->getMethod($method_name);
    $this->processArgumentValues($plugin, $method_name, $arguments);
    $plugin->disableCache();
    return $method->invokeArgs($plugin, $arguments ?? []);
  }

  /**
   * Get the fabric client.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryPluginInterface
   *   The fabric query plugin.
   */
  private function getFabricQuery(string $plugin_id): FabricQueryPluginInterface {
    return self::getFabricQueryManager()->createInstance($plugin_id);
  }

  /**
   * Get the fabric client.
   *
   * @return array
   *   An array of fabric query plugin definitions.
   */
  private function getFabricQueryDefinitions(): array {
    $plugins = self::getFabricQueryManager()->getDefinitions();
    $plugins = array_filter($plugins, fn (array $plugin): bool => !str_contains($plugin['class'], '\\Import\\'));
    ksort($plugins);
    return $plugins;
  }

  /**
   * Cast a value for printing via print_r.
   *
   * @param mixed $value
   *   The input value.
   *
   * @return string|array
   *   The casted value.
   */
  private function castValue($value) {
    if (is_object($value) && method_exists($value, 'toArray')) {
      $value = $value->toArray();
    }
    elseif (is_object($value) && $value instanceof \Stringable) {
      $value = (string) $value;
    }
    elseif (is_array($value)) {
      foreach ($value as &$item) {
        $item = $this->castValue($item);
      }
    }
    return $value;
  }

  /**
   * Get the fabric query manager.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryManager
   *   The fabric query manager.
   */
  private static function getFabricQueryManager(): FabricQueryManager {
    /** @var \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager */
    $fabric_query_manager = \Drupal::service('plugin.manager.fabric_query_manager');
    return $fabric_query_manager;
  }

}
