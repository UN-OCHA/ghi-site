<?php

namespace Drupal\hpc_api\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_api\Query\FabricQueryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to inspect and run Fabric query plugin methods.
 *
 * The inspector reflects query plugins into a small admin UI, resolves simple
 * scalar and object arguments from form input, and displays the raw and casted
 * method result alongside the GraphQL requests made by the method.
 */
class FabricQueryInspectorForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The fabric query manager.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  protected $fabricQueryManager;

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * Constructs a new FabricQueryInspectorForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager
   *   The fabric query manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   The private tempstore factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FabricQueryManager $fabric_query_manager, PrivateTempStoreFactory $private_temp_store_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fabricQueryManager = $fabric_query_manager;
    $this->privateTempStoreFactory = $private_temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.fabric_query_manager'),
      $container->get('tempstore.private'),
    );
  }

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
    // Successful submissions redirect back to this form, so the previous result
    // and submitted values are restored from tempstore on the GET request.
    $stored_result = $this->getStoredResult();
    $plugin_options = $this->getPluginOptions();
    $plugin_id = $form_state->getValue('plugin_id') ?: ($stored_result['plugin_id'] ?? array_key_first($plugin_options));
    $plugin_id = !empty($plugin_options[$plugin_id]) ? $plugin_id : array_key_first($plugin_options);
    $plugin = $this->getFabricQuery($plugin_id);

    $method_options = $this->getMethodOptions($plugin);
    $method_name = $form_state->getValue('method_name') ?? ($stored_result['method_name'] ?? NULL);
    $method_name = ($method_name && !empty($method_options[$method_name])) ? $method_name : array_key_first($method_options);
    $method_supported = $plugin && $method_name ? $this->methodHasSupportedArguments(new \ReflectionMethod(get_class($plugin), $method_name)) : FALSE;

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

    $arguments = $plugin && $method_name && $method_supported ? $this->getArguments($plugin, $method_name) : [];
    $stored_arguments = $stored_result['arguments'][$method_name] ?? [];
    $submitted_arguments = array_filter($form_state->getValue(['arguments', $method_name], $stored_arguments), static fn ($value) => $value !== NULL && $value !== '');
    $form['arguments'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['arguments'][$method_name] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($arguments as $position => $argument) {
      $form['arguments'][$method_name][$position] = $this->buildArgumentElement($argument, $submitted_arguments[$position] ?? NULL);
    }

    // Keep methods with unsupported required arguments visible for discovery,
    // but prevent submitting a method the inspector cannot invoke safely.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'style' => 'margin-bottom: 2rem;',
      ],
      '#disabled' => !($plugin && $method_name && $method_supported),
    ];

    // Drupal select options cannot be disabled individually here, so explain
    // the disabled submit button when an unsupported method is selected.
    if ($plugin && $method_name && !$method_supported) {
      $form['unsupported_method'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
        'message' => [
          '#markup' => $this->t('This method has required arguments that the inspector cannot build yet.'),
        ],
      ];
    }

    if ($result = $stored_result) {
      $form['meta'] = [
        '#type' => 'details',
        '#title' => $this->t('Meta'),
        '#open' => FALSE,
        'children' => [
          [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => $this->t('The method call took @duration seconds', [
              '@duration' => $result['duration'],
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
            }, array_keys($result['queries']), $result['queries']),
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
          '#value' => empty($result['error']) ? print_r($this->castValue($result['value']), TRUE) : print_r($result['error'], TRUE),
        ],
      ];
      $form['result_json'] = [
        '#type' => 'details',
        '#title' => $this->t('Result (json)'),
        '#open' => FALSE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($result['error']) ? json_encode($this->castValue($result['value']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : print_r($result['error'], TRUE),
        ],
      ];
      $form['result_original'] = [
        '#type' => 'details',
        '#title' => $this->t('Result (original)'),
        '#open' => FALSE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($result['error']) ? print_r($result['value'], TRUE) : print_r($result['error'], TRUE),
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
    $plugin_options = $this->getPluginOptions();
    $plugin_id = $form_state->getValue('plugin_id') ?: array_key_first($plugin_options);
    $plugin = $this->getFabricQuery($plugin_id);

    $method_options = $this->getMethodOptions($plugin);
    $method_name = $form_state->getValue('method_name') ?? NULL;
    $method_name = ($method_name && !empty($method_options[$method_name])) ? $method_name : array_key_first($method_options);
    // Unsupported methods stay visible in the selector, but the disabled
    // submit button is only a UI guard. Recheck here in case of stale form
    // state or a crafted submission.
    if (!$this->methodHasSupportedArguments(new \ReflectionMethod(get_class($plugin), $method_name))) {
      $form_state->setRebuild();
      return;
    }

    $submitted_arguments = array_filter($form_state->getValue(['arguments', $method_name], []), static fn ($value) => $value !== NULL && $value !== '');
    $start = microtime(TRUE);
    $error = NULL;
    $value = NULL;
    try {
      $value = $this->callPluginMethod($plugin, $method_name, $submitted_arguments);
    }
    catch (\Throwable $e) {
      $error = $e->getMessage();
      $this->messenger()->addError($error);
    }
    // Store both the result and form defaults before redirecting. This avoids a
    // POST refresh while keeping the inspector state.
    $this->setStoredResult([
      'plugin_id' => $plugin_id,
      'method_name' => $method_name,
      'arguments' => [
        $method_name => $submitted_arguments,
      ],
      'duration' => microtime(TRUE) - $start,
      'queries' => QueryHelper::endpointCallTimeStorage(),
      'value' => $value,
      'error' => $error,
    ]);
    $form_state->setRedirect('hpc_api.reports.fabric.query_inspector');
  }

  /**
   * Get the stored inspector result for the current user.
   *
   * @return array|null
   *   The stored result.
   */
  private function getStoredResult(): ?array {
    return $this->privateTempStoreFactory->get('hpc_api.fabric_query_inspector')->get('result');
  }

  /**
   * Store the inspector result for the current user.
   *
   * @param array $result
   *   The result to store.
   */
  private function setStoredResult(array $result): void {
    $this->privateTempStoreFactory->get('hpc_api.fabric_query_inspector')->set('result', $result);
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
    $exclude_names = ['create', 'setYear', 'getYear'];
    $options = [];
    foreach ($methods as $method) {
      // Only expose methods implemented in the plugin class itself. Reflection
      // reports trait methods as class methods, so the file check matters.
      if (!$this->methodIsDeclaredInClass($method, $class)) {
        continue;
      }
      if (in_array($method->getName(), $exclude_names)) {
        continue;
      }
      if ($this->methodReturnsFabricQuery($method)) {
        continue;
      }
      $options[$method->getName()] = $this->methodHasSupportedArguments($method) ? $method->getName() : $this->t('@method (unsupported arguments)', [
        '@method' => $method->getName(),
      ]);
    }
    return $options;
  }

  /**
   * Check whether a method is declared directly in the given class.
   *
   * Trait methods are reflected as methods on the using class, so the method
   * file must also match the class file.
   *
   * @param \ReflectionMethod $method
   *   The method to check.
   * @param \ReflectionClass $class
   *   The class to check against.
   *
   * @return bool
   *   TRUE if the method is declared directly in the class.
   */
  private function methodIsDeclaredInClass(\ReflectionMethod $method, \ReflectionClass $class): bool {
    return $method->isPublic()
      && $method->getDeclaringClass()->getName() == $class->getName()
      && $method->getFileName() == $class->getFileName();
  }

  /**
   * Check if a method returns a Fabric query builder object.
   *
   * @param \ReflectionMethod $method
   *   The method to check.
   *
   * @return bool
   *   TRUE if the method returns a FabricQuery object.
   */
  private function methodReturnsFabricQuery(\ReflectionMethod $method): bool {
    $return_type = $method->getReturnType();
    if (!$return_type) {
      return FALSE;
    }
    return in_array(FabricQuery::class, $this->getTypeNames($return_type));
  }

  /**
   * Check if a method has arguments that the inspector can submit safely.
   *
   * @param \ReflectionMethod $method
   *   The method to check.
   *
   * @return bool
   *   TRUE if all required arguments are supported.
   */
  private function methodHasSupportedArguments(\ReflectionMethod $method): bool {
    foreach ($method->getParameters() as $parameter) {
      if ($this->isSupportedParameter($parameter)) {
        continue;
      }
      if (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull()) {
        return FALSE;
      }
    }
    return TRUE;
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
    $options = [];
    foreach ($method->getParameters() as $arg) {
      if (!$this->isSupportedParameter($arg)) {
        continue;
      }
      $options[$arg->getPosition()] = [
        'position' => $arg->getPosition(),
        'name' => $arg->getName(),
        'type' => $arg->getType(),
        'null' => $arg->allowsNull(),
        'required' => !$arg->isDefaultValueAvailable() && !$arg->allowsNull(),
        'default_value' => $arg->isOptional() ? $arg->getDefaultValue() : NULL,
      ];
    }
    return $options;
  }

  /**
   * Check if the given parameter is supported by the inspector.
   *
   * @param \ReflectionParameter $parameter
   *   The parameter to check.
   *
   * @return bool
   *   TRUE if the parameter is supported.
   */
  private function isSupportedParameter(\ReflectionParameter $parameter): bool {
    $type = $parameter->getType();
    return $type && !empty(array_filter($this->getTypeNames($type), fn (string $type_name): bool => $this->isSupportedType($type_name)));
  }

  /**
   * Get the named types from a reflection type declaration.
   *
   * @param \ReflectionType $type
   *   The type declaration.
   *
   * @return string[]
   *   The type names.
   */
  private function getTypeNames(\ReflectionType $type): array {
    if ($type instanceof \ReflectionNamedType) {
      return [$type->getName()];
    }
    if ($type instanceof \ReflectionUnionType) {
      return array_map(static fn (\ReflectionNamedType $type): string => $type->getName(), $type->getTypes());
    }
    return [];
  }

  /**
   * Check if the given type can be entered in the inspector.
   *
   * @param string $type_name
   *   The type name.
   *
   * @return bool
   *   TRUE if supported.
   */
  private function isSupportedType(string $type_name): bool {
    return in_array($type_name, ['int', 'string', 'array', 'bool']) || $this->getApiObjectResolver($type_name) || $this->isSupportedEntityType($type_name);
  }

  /**
   * Build a form element for a reflected argument.
   *
   * @param array $argument
   *   The argument definition.
   * @param mixed $submitted_value
   *   The submitted value.
   *
   * @return array
   *   A form element.
   */
  private function buildArgumentElement(array $argument, mixed $submitted_value): array {
    $type_names = $this->getTypeNames($argument['type']);
    $title = ucfirst(str_replace('_', ' ', $argument['name']));
    $description = implode(', ', array_filter([
      $argument['type'],
      $argument['required'] ? 'required' : 'optional',
      $argument['default_value'] !== NULL ? ('default: ' . print_r($argument['default_value'], TRUE)) : NULL,
    ]));
    $element = [
      '#title' => $title,
      '#description' => $description,
    ];
    if (in_array('bool', $type_names)) {
      $element += [
        '#type' => 'checkbox',
        '#default_value' => (bool) ($submitted_value ?? $argument['default_value'] ?? FALSE),
      ];
      return $element;
    }
    if ($entity_type = $this->getSupportedEntityType($type_names)) {
      // Drupal entity arguments are entered as base_object autocomplete values;
      // the submitted entity id is resolved back to the expected typed object.
      $default_value = $submitted_value ? $this->loadBaseObject((int) $submitted_value) : NULL;
      $element += [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'base_object',
        '#default_value' => $default_value,
      ];
      if ($entity_type == 'plan') {
        $element['#selection_settings']['target_bundles'] = ['plan'];
      }
      return $element;
    }
    $element += [
      '#type' => 'textfield',
      '#default_value' => $submitted_value ?? NULL,
    ];
    if ($this->getSupportedApiObjectType($type_names)) {
      $element['#description'] .= ' ' . $this->t('Enter the Fabric object id.');
    }
    return $element;
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
      if ($value === '' || $value === NULL) {
        unset($values[$position]);
        continue;
      }
      if ($this->argumentAllowsType($type, 'array')) {
        if (str_contains($value, ',')) {
          $value = explode(',', $value);
        }
        else {
          $value = [$value];
        }
      }
      elseif ($this->argumentAllowsType($type, 'int')) {
        $value = (int) $value;
      }
      elseif ($this->argumentAllowsType($type, 'string')) {
        $value = (string) $value;
      }
      elseif ($this->argumentAllowsType($type, 'bool')) {
        $value = (bool) $value;
      }
      else {
        $value = $this->resolveObjectArgument($type, $value);
      }
    }
  }

  /**
   * Check whether an argument type declaration allows the given type.
   *
   * @param \ReflectionType $type
   *   The argument type declaration.
   * @param string $type_name
   *   The type name to check.
   *
   * @return bool
   *   TRUE if the type is allowed, FALSE otherwise.
   */
  private function argumentAllowsType(\ReflectionType $type, string $type_name): bool {
    return in_array($type_name, $this->getTypeNames($type));
  }

  /**
   * Resolve an object argument from a submitted scalar value.
   *
   * @param \ReflectionType $type
   *   The argument type.
   * @param mixed $value
   *   The submitted value.
   *
   * @return mixed
   *   The resolved object.
   *
   * @throws \InvalidArgumentException
   */
  private function resolveObjectArgument(\ReflectionType $type, mixed $value): mixed {
    foreach ($this->getTypeNames($type) as $type_name) {
      // Prefer concrete entity resolution before Fabric API lookups because
      // ContentEntityInterface-compatible types are local Drupal entities.
      if ($this->isSupportedEntityType($type_name)) {
        return $this->resolveEntityArgument($type_name, $value);
      }
      if ($resolver = $this->getApiObjectResolver($type_name)) {
        return $this->resolveApiObjectArgument($type_name, $resolver, $value);
      }
    }
    return NULL;
  }

  /**
   * Resolve a Drupal base object argument.
   *
   * @param string $type_name
   *   The expected type name.
   * @param mixed $value
   *   The submitted entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The resolved entity.
   *
   * @throws \InvalidArgumentException
   */
  private function resolveEntityArgument(string $type_name, mixed $value) {
    $entity = $this->loadBaseObject((int) $value);
    if (!$entity || !$entity instanceof $type_name) {
      throw new \InvalidArgumentException(sprintf('Could not resolve %s from base object id %s.', $type_name, $value));
    }
    return $entity;
  }

  /**
   * Resolve a Fabric API object argument.
   *
   * @param string $type_name
   *   The expected type name.
   * @param array $resolver
   *   The resolver metadata.
   * @param mixed $value
   *   The submitted Fabric object id.
   *
   * @return mixed
   *   The resolved API object.
   *
   * @throws \InvalidArgumentException
   */
  private function resolveApiObjectArgument(string $type_name, array $resolver, mixed $value): mixed {
    $plugin = $this->getFabricQuery($resolver['plugin']);
    $object = $plugin->{$resolver['method']}((int) $value);
    if (!$object || !$object instanceof $type_name) {
      throw new \InvalidArgumentException(sprintf('Could not resolve %s from Fabric id %s.', $type_name, $value));
    }
    return $object;
  }

  /**
   * Get the first supported entity type from a list of type names.
   *
   * @param string[] $type_names
   *   The type names.
   *
   * @return string|null
   *   The supported entity type, if any.
   */
  private function getSupportedEntityType(array $type_names): ?string {
    foreach ($type_names as $type_name) {
      if ($this->isSupportedEntityType($type_name)) {
        return $type_name == 'Drupal\ghi_plans\Entity\Plan' ? 'plan' : 'base_object';
      }
    }
    return NULL;
  }

  /**
   * Check if a type can be resolved as a base object entity.
   *
   * @param string $type_name
   *   The type name.
   *
   * @return bool
   *   TRUE if supported.
   */
  private function isSupportedEntityType(string $type_name): bool {
    return in_array($type_name, [
      'Drupal\Core\Entity\ContentEntityInterface',
      'Drupal\ghi_base_objects\Entity\BaseObject',
      'Drupal\ghi_base_objects\Entity\BaseObjectInterface',
      'Drupal\ghi_base_objects\Entity\BaseObjectChildInterface',
      'Drupal\ghi_plans\Entity\Plan',
    ]);
  }

  /**
   * Load a base object by id.
   *
   * @param int $id
   *   The base object id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The base object entity.
   */
  private function loadBaseObject(int $id) {
    return $this->entityTypeManager->getStorage('base_object')->load($id);
  }

  /**
   * Get the first supported API object type from a list of type names.
   *
   * @param string[] $type_names
   *   The type names.
   *
   * @return string|null
   *   The supported type name, if any.
   */
  private function getSupportedApiObjectType(array $type_names): ?string {
    foreach ($type_names as $type_name) {
      if ($this->getApiObjectResolver($type_name)) {
        return $type_name;
      }
    }
    return NULL;
  }

  /**
   * Get resolver metadata for a supported API object type.
   *
   * @param string $type_name
   *   The type name.
   *
   * @return array|null
   *   Resolver metadata, if supported.
   */
  private function getApiObjectResolver(string $type_name): ?array {
    // API objects are resolved by id through the query plugin that owns that
    // object family, instead of trying to reconstruct objects from form input.
    $resolvers = [
      'Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface' => [
        'plugin' => 'attachment',
        'method' => 'getAttachment',
      ],
      'Drupal\ghi_plans\ApiObjects\Organization' => [
        'plugin' => 'organization',
        'method' => 'getOrganization',
      ],
      'Drupal\ghi_plans\ApiObjects\Plan' => [
        'plugin' => 'plan',
        'method' => 'getPlan',
      ],
      'Drupal\ghi_plans\ApiObjects\Project' => [
        'plugin' => 'project',
        'method' => 'getProject',
      ],
    ];
    return $resolvers[$type_name] ?? NULL;
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
    if ($arguments) {
      $this->processArgumentValues($plugin, $method_name, $arguments);
    }
    $arguments = $this->buildInvocationArguments($plugin, $method_name, $arguments ?? []);
    $plugin->disableCache();
    return $method->invokeArgs($plugin, $arguments ?? []);
  }

  /**
   * Build a positional argument list for reflection invocation.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryPluginInterface $plugin
   *   The query plugin.
   * @param string $method_name
   *   The method name.
   * @param array $submitted_arguments
   *   The processed submitted arguments.
   *
   * @return array
   *   The positional argument list.
   */
  private function buildInvocationArguments(FabricQueryPluginInterface $plugin, string $method_name, array $submitted_arguments): array {
    $arguments = $this->getArguments($plugin, $method_name);
    $last_position = empty($submitted_arguments) ? -1 : max(array_keys($submitted_arguments));
    $invoke_arguments = [];
    foreach ($arguments as $position => $argument) {
      if ($position > $last_position) {
        break;
      }
      if (array_key_exists($position, $submitted_arguments)) {
        $invoke_arguments[$position] = $submitted_arguments[$position];
      }
      elseif ($argument['default_value'] !== NULL) {
        // Fill skipped optional arguments so later submitted arguments keep
        // their original reflected position when passed to invokeArgs().
        $invoke_arguments[$position] = $argument['default_value'];
      }
      else {
        $invoke_arguments[$position] = NULL;
      }
    }
    ksort($invoke_arguments);
    return $invoke_arguments;
  }

  /**
   * Get a fabric query plugin instance.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryPluginInterface
   *   The fabric query plugin.
   */
  private function getFabricQuery(string $plugin_id): FabricQueryPluginInterface {
    return $this->fabricQueryManager->createInstance($plugin_id);
  }

  /**
   * Get fabric query plugin definitions.
   *
   * @return array
   *   An array of fabric query plugin definitions.
   */
  private function getFabricQueryDefinitions(): array {
    $plugins = $this->fabricQueryManager->getDefinitions();
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

}
