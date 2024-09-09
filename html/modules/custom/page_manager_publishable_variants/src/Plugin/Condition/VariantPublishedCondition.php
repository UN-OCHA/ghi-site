<?php

namespace Drupal\page_manager_publishable_variants\Plugin\Condition;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\page_manager\Entity\PageVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Variant published' condition.
 *
 * @Condition(
 *   id = "variant_published_condition",
 *   label = @Translation("Variant published"),
 * )
 */
class VariantPublishedCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SharedTempStoreFactory $tempstore, RouteMatchInterface $route_match, RequestStack $request_stack, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->tempstore = $tempstore;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
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
      $container->get('request_stack'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['variant_states' => ''] + parent::defaultConfiguration();
  }

  /**
   * Get the variants for the page.
   */
  private function getPageVariants() {
    $machine_name = $this->routeMatch->getParameter('machine_name');
    if (!$machine_name) {
      return [];
    }
    // HPC-6466
    // Reason for using $route_match->getParameter('tempstore_id') instead of
    // 'page_manager.page' is that while adding a new variant, the routeMatch()
    // refers to 'page_manager.variant' and if not done this way, we could not
    // get the page parameters.
    $page_tempstore = $this->tempstore->get($this->routeMatch->getParameter('tempstore_id'))->get($machine_name);
    if (empty($page_tempstore['page'])) {
      return [];
    }
    /** @var \Drupal\page_manager\Entity\Page $page */
    $page = $page_tempstore['page'];
    return $page->getVariants();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $page_variants = $this->getPageVariants();
    if (empty($page_variants)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup('<p>@message</p>', ['@message' => $this->t('No page variants available yet.')]),
      ];
      return $form;
    }
    else {
      $form['variant_states'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Published variants'),
        '#options' => array_map(function ($page_variant) {
          /** @var \Drupal\page_manager\Entity\PageVariant $page_variant */
          return $page_variant->label();
        }, $page_variants),
        '#default_value' => $this->configuration['variant_states'],
        '#multiple' => TRUE,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['variant_states'] = $form_state->getValue('variant_states');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $published_variants = array_filter($this->configuration['variant_states']);

    $page_variants = array_map(function ($variant) {
      return $variant->label();
    }, array_intersect_key($this->getPageVariants(), $published_variants));

    $arguments = [
      '@published' => $page_variants ? implode(', ', $page_variants) : $this->t('None selected'),
    ];
    if (!empty($this->configuration['negate'])) {
      return $this->t('Unpublished variants <em>@published</em>', $arguments);
    }
    return $this->t('Published variants: <em>@published</em>', $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $variant = $this->getCurrentPageVariant($this->requestStack->getCurrentRequest(), $this->routeMatch);
    return $this->hasAccess($variant->id());
  }

  /**
   * Check if access to the given variant id should be granted.
   *
   * @param string $variant_id
   *   The variant id.
   *
   * @return bool
   *   TRUE if access should be granted, FALSE otherwise.
   */
  public function hasAccess($variant_id) {
    $published_variants = array_filter($this->configuration['variant_states']);
    if ($this->currentUser->hasPermission('access unpublished page variants')) {
      return TRUE;
    }

    if (empty($published_variants) && !$this->isNegated()) {
      return FALSE;
    }

    if ($this->isNegated()) {
      return !in_array($variant_id, $published_variants);
    }
    return in_array($variant_id, $published_variants);
  }

  /**
   * Get the current page variant from the given route match object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\page_manager\Entity\PageVariant|null
   *   The page variant if found.
   */
  private function getCurrentPageVariant(Request $request, RouteMatchInterface $route_match) {

    if ($request->attributes->has('_page_manager_page_variant')) {
      return $request->attributes->get('_page_manager_page_variant');
    }

    // Otherwise we might be in the page manager config UI.
    $variant_id = NULL;
    $page_parameters = $route_match->getRawParameters()->all();
    if (array_key_exists('machine_name', $page_parameters) && array_key_exists('step', $page_parameters)) {
      // The step parameter looks like this and holds the variant id:
      // page_variant__homepage-layout_builder-0__layout_builder
      // The variant id in this case is "homepage-layout_builder-0".
      $variant_id = explode('__', $page_parameters['step'])[1];
    }
    elseif (array_key_exists('section_storage_type', $page_parameters) && $page_parameters['section_storage_type'] == 'page_manager') {
      $variant_id = $page_parameters['section_storage'];
    }

    return $variant_id ? PageVariant::load($variant_id) : NULL;
  }

}
