<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hpc_common\Plugin\Condition\PageParameterCondition;
use Drupal\hpc_common\Traits\PageManagerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for extracting values from selection criteria conditions.
 *
 * This is of use in the case of page manager edit pages that use layout
 * builder and should allow the preview of views blocks, which expect
 * contextual arguments. Normally, they would receive no arguments at all, but
 * we can make it so that the arguments are extracted from the selection
 * criteria conditions (visible only on pages with a year argument and a value
 * of "2021" for example, in which case we would pass the "2021" as a context
 * value to the views block).
 *
 * @see Drupal\ghi_blocks\EventSubscriber\LayoutBuilderRouteSubscriber
 */
class SelectionCriteriaArgument {

  use PageManagerTrait;

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
   * Constructs a new RouteCacheContext class.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RouteMatchInterface $route_match, RequestStack $request_stack) {
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * Try to find an argument based on the current route match.
   *
   * @return mixed|null
   *   A value or NULL if no year can be found.
   */
  public function getArgumentFromSelectionCriteria($name) {
    $page_variant = $this->getCurrentPageVariant($this->requestStack->getCurrentRequest(), $this->routeMatch);

    if (empty($page_variant)) {
      // Strange, but better check and bail out.
      return NULL;
    }
    // Now get the selection criteria and see if one of them represents a
    // page parameter condition that holds a year.
    $plugin_collection = $page_variant->getPluginCollections();
    $selection_criteria = $plugin_collection['selection_criteria'];
    foreach ($selection_criteria as $selection_criteria) {
      if (!$selection_criteria instanceof PageParameterCondition) {
        continue;
      }
      /** @var \Drupal\hpc_common\Plugin\Condition\PageParameterCondition $selection_criteria */
      $configuration = $selection_criteria->getConfiguration();
      if ($configuration['parameter'] == $name) {
        // Gotcha.
        return $configuration['value'];
      }
    }
    return NULL;
  }

}
