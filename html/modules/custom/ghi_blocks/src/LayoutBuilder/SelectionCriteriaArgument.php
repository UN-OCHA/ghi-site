<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hpc_common\Plugin\Condition\PageParameterCondition;
use Drupal\page_manager\Entity\PageVariant;

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

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new RouteCacheContext class.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Try to find an argument based on the current route match.
   *
   * @return mixed|null
   *   A value or NULL if no year can be found.
   */
  public function getArgumentFromSelectionCriteria($name) {
    $page_parameters = $this->routeMatch->getRawParameters()->all();

    $variant_id = NULL;
    if (array_key_exists('machine_name', $page_parameters) && array_key_exists('step', $page_parameters)) {
      // The step parameter looks like this and holds the variant id:
      // page_variant__homepage-layout_builder-0__layout_builder
      // The variant id in this case is "homepage-layout_builder-0".
      $variant_id = explode('__', $page_parameters['step'])[1];
    }
    elseif (array_key_exists('section_storage_type', $page_parameters) && $page_parameters['section_storage_type'] == 'page_manager') {
      $variant_id = $page_parameters['section_storage'];
    }

    if (!$variant_id) {
      return NULL;
    }

    $page_variant = PageVariant::load($variant_id);
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
