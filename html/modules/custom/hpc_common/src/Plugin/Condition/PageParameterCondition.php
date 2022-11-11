<?php

namespace Drupal\hpc_common\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Page parameter' condition.
 *
 * @Condition(
 *   id = "page_parameter_condition",
 *   label = @Translation("Page parameter"),
 * )
 */
class PageParameterCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a Context condition plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore to use during configurations.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SharedTempStoreFactory $tempstore, RouteMatchInterface $route_match, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempstore = $tempstore;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.shared'),
      $container->get('current_route_match'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['parameter' => '', 'value' => ''] + parent::defaultConfiguration();
  }

  /**
   * Get the parameters for the current request.
   */
  private function getParameters() {
    $route_match = $this->routeMatch;
    $machine_name = $route_match->getParameter('machine_name');
    if (!$machine_name) {
      return [];
    }
    // HPC-6466
    // Reason for using $route_match->getParameter('tempstore_id') instead of
    // 'page_manager.page' is that while adding a new variant, the routeMatch()
    // refers to 'page_manager.variant' and if not done this way, we could not
    // get the page parameters.
    $page_tempstore = $this->tempstore->get($route_match->getParameter('tempstore_id'))->get($machine_name);
    if (empty($page_tempstore['page'])) {
      return [];
    }
    return array_filter($page_tempstore['page']->getParameters(), function ($parameter) {
      return $parameter['type'] == 'string' || $parameter['type'] == 'integer';
    });
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $parameters = $this->getParameters();
    if (empty($parameters)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup('<p>@message</p>', ['@message' => $this->t('No page parameters available in this context')]),
      ];
      return $form;
    }
    else {
      $form['parameter'] = [
        '#type' => 'select',
        '#title' => $this->t('Page parameter'),
        '#options' => array_map(function ($parameter) {
          return $parameter['label'];
        }, $parameters),
        '#default_value' => $this->configuration['parameter'],
        '#required' => TRUE,
      ];
      $form['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#default_value' => $this->configuration['value'],
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['parameter'] = $form_state->getValue('parameter');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $arguments = [
      '@parameter' => $this->configuration['parameter'],
      '@value' => trim($this->configuration['value']),
    ];
    if (!empty($this->configuration['negate'])) {
      return $this->t('Do not return true if <em>@parameter</em> is <em>@value</em>', $arguments);
    }
    return $this->t('Return true if <em>@parameter</em> is <em>@value</em>', $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $parameter = $this->configuration['parameter'];
    $value = $this->configuration['value'];
    if (!$parameter) {
      return TRUE;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request->attributes->has($parameter)) {
      return $value == $request->attributes->get($parameter);
    }

    return TRUE;
  }

}
