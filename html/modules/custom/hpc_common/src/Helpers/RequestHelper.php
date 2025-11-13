<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\panels_ipe\Form\PanelsIPEBlockPluginForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper class for everything request related.
 */
class RequestHelper {

  /**
   * Get the node object from the current request if possible.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node object or NULL if none is available.
   */
  public static function getCurrentNodeObject() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof NodeInterface || !$node->getFieldDefinitions()) {
      return;
    }
    return $node;
  }

  /**
   * Get the entity object from the current request if possible.
   *
   * @return \Drupal\core\Entity\EntityInterface|null
   *   The entity object or NULL if none is available.
   */
  public static function getCurrentEntityObject() {
    $route_match = \Drupal::routeMatch();
    // Entity will be found in the route parameters.
    if (($route = $route_match->getRouteObject()) && ($parameters = $route->getOption('parameters'))) {
      // Determine if the current route represents an entity.
      foreach ($parameters as $name => $options) {
        if (isset($options['type']) && strpos($options['type'], 'entity:') === 0) {
          $entity = $route_match->getParameter($name);
          if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
            return $entity;
          }

          // Since entity was found, no need to iterate further.
          return NULL;
        }
      }
    }
  }

  /**
   * Get the current route arguments.
   *
   * @return array
   *   An array of parameters.
   */
  public static function getCurrentRouteArguments() {
    $route_match = \Drupal::routeMatch();
    return $route_match->getParameters()->all();
  }

  /**
   * Get a query argument from the current request.
   *
   * @param string $name
   *   The name of the query argument to retrieve.
   * @param string $arguments
   *   An optional arguments array that takes recedence over the actual
   *   request.
   *
   * @return mixed
   *   The query argument if it has been found.
   */
  public static function getQueryArgument($name, $arguments = NULL) {
    if (!empty($arguments) && !empty($arguments[$name])) {
      return $arguments[$name];
    }
    if (!\Drupal::request()->query->has($name)) {
      return NULL;
    }
    return \Drupal::request()->query->get($name);
  }

  /**
   * Flatten the given query array into a string.
   *
   * @param array $query
   *   A query array.
   *
   * @return string
   *   The query string.
   */
  public static function flattenQuery(array $query) {
    return http_build_query($query);
  }

  /**
   * Retrieve additional context values based on the path.
   *
   * @param string $path
   *   The path to the page being edited using IPE.
   *
   * @return \Drupal\Core\Plugin\Context\Context[]
   *   The extracted contexts.
   */
  public static function getContextsForPath($path) {
    $request = Request::create('/' . ltrim($path, '/'));
    /** @var \Drupal\Core\Routing\Router $router */
    $router = \Drupal::service('router.no_access_checks');
    try {
      $result = $router->matchRequest($request);
    }
    catch (\Exception $e) {
      return [];
    }
    $contexts = [];
    if (empty($result['_page_manager_page'])) {
      if (!empty($result['node'])) {
        $contexts['node'] = EntityContext::fromEntity($result['node'], t('Node'));
      }
      return $contexts;
    }

    $route = $result['_route_object'];
    $page = $result['_page_manager_page'];

    if ($route && $route_contexts = $route->getOption('parameters')) {
      foreach ($route_contexts as $route_context_name => $route_context) {
        // Skip this parameter.
        if ($route_context_name == '_page_manager_page_variant' || $route_context_name == '_page_manager_page') {
          continue;
        }

        $parameter = $page->getParameter($route_context_name);
        $context_name = !empty($parameter['label']) ? $parameter['label'] : t('{@name} from route', ['@name' => $route_context_name]);
        if ($request->attributes->has($route_context_name)) {
          $value = $request->attributes->get($route_context_name);
        }
        else {
          $value = NULL;
        }
        $cacheability = new CacheableMetadata();
        $cacheability->setCacheContexts(['route']);

        $context = new Context(new ContextDefinition($route_context['type'], $context_name, FALSE), $value);
        $context->addCacheableDependency($cacheability);

        $contexts[$route_context_name] = $context;
      }
    }
    return $contexts;
  }

  /**
   * Retrieve additional context values based on the path.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The path to the page being edited using IPE.
   *
   * @return \Drupal\Core\Plugin\Context\Context[]
   *   The extracted contexts.
   */
  public static function getContextsForPlanNode(Node $node) {
    $contexts = [];

    $context = new Context(new ContextDefinition('integer', t('Year'), FALSE), $node->get('field_plan_year')->value);
    $contexts['year'] = $context;

    $context = new Context(new ContextDefinition('integer', t('Plan ID'), FALSE), $node->get('field_original_id')->value);
    $contexts['plan_id'] = $context;

    return $contexts;
  }

  /**
   * Retrieve additional context values based on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object currently being edited.
   *
   * @return \Drupal\Core\Plugin\Context\Context[]
   *   The extracted contexts.
   */
  public static function getContextsFromFormState(FormStateInterface $form_state) {

    $request_stack = \Drupal::service('request_stack');
    $current_path = $request_stack->getCurrentRequest()->request->get('currentPath');
    // Get the build information and setup required variables supporting
    // 2 different situations.
    if (!empty($current_path)) {
      return self::getContextsForPath($current_path);
    }

    $build_info = $form_state->getBuildInfo();
    if (empty($build_info['args'])) {
      return [];
    }

    $contexts = [];
    if ($build_info['args'][0] == 'page_manager.page') {
      return $contexts;
    }
    elseif ($build_info['callback_object'] instanceof PanelsIPEBlockPluginForm) {
      // Panels.
      if (!empty($current_path)) {
        $contexts = self::getContextsForPath($current_path);
      }
    }
    else {
      // @todo Verify that this works, e.g. with plan nodes.
      // Layout builder.
      if ($form_state instanceof SubformStateInterface) {
        $all_contexts = $form_state->getCompleteFormState()->getTemporaryValue('gathered_contexts');
      }
      else {
        $all_contexts = $form_state->getTemporaryValue('gathered_contexts');
      }
      $node = $all_contexts['layout_builder.entity']->getContextValue();
      $contexts = self::getContextsForPlanNode($node);
    }
    return $contexts;
  }

}
