<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Url;
use Drupal\views\Views;
use Drupal\views\ViewExecutable;

use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;

/**
 * Helper class for Views.
 */
class ViewsHelper {

  /**
   * Load all available block displays for the given view id.
   *
   * @param string $view_id
   *   The view id for which to look up the displays.
   * @param string $display_type
   *   Restrict to this display type.
   * @param string $exclude_pattern
   *   An exclude pattern for the start of the string.
   *
   * @return array
   *   Array of display definitions keyed by the display id.
   */
  public static function getDisplaysForViewId($view_id, $display_type = 'block', $exclude_pattern = NULL) {
    $displays = &drupal_static(__FUNCTION__, []);
    if (empty($displays[$view_id])) {
      $displays[$view_id] = [];
      $view = Views::getView($view_id);
      if ($view) {
        $displays[$view_id] = self::getDisplaysForView($view, $display_type, $exclude_pattern);
      }
    }
    return $displays[$view_id];
  }

  /**
   * Load all available block displays for the given view id.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view for which to look up the displays.
   * @param string $display_type
   *   Restrict to this display type.
   * @param string $exclude_pattern
   *   An exclude pattern for the start of the string.
   *
   * @return array
   *   Array of display definitions keyed by the display id.
   */
  public static function getDisplaysForView(ViewExecutable $view, $display_type = 'block', $exclude_pattern = NULL) {
    $displays = &drupal_static(__FUNCTION__, []);
    $view_id = $view->id();
    if (empty($displays[$view_id])) {
      $displays[$view_id] = [];
      if ($view) {
        foreach ($view->storage->get('display') as $id => $display) {
          if ($display['display_plugin'] != $display_type || $id == 'default') {
            continue;
          }
          if ($exclude_pattern !== NULL && strpos($id, $exclude_pattern) === 0) {
            continue;
          }
          $displays[$view_id][$id] = $display;
        }
        ArrayHelper::sortArray($displays[$view_id], 'position', EndpointQuery::SORT_ASC);
      }
    }
    return $displays[$view_id];
  }

  /**
   * Get an instantiated view based on the given arguments.
   *
   * @param string $uri
   *   The URI where the view instance should live.
   * @param string $view_id
   *   The view id.
   * @param string $view_display
   *   The desired view display.
   * @param array $query
   *   Additional query arguments.
   *
   * @return \Drupal\views\ViewExecutable
   *   An instance of the views executable if found.
   */
  public static function getViewInstance($uri, $view_id, $view_display, array $query = []) {

    // Load the views object.
    $view = Views::getView($view_id);
    if (!is_object($view)) {
      return NULL;
    }

    // Set up basic state for this views. BUT DON'T BUILD IT! Otherwhise view
    // execution later will run into trouble with customization, e.g. the
    // column select feature that lets users customize the columns they see and
    // we expect those changes to be also reflected in the download.
    $view->setDisplay($view_display);
    $view->initHandlers();
    // Add in the arguments.
    /** @var \Drupal\Core\Routing\Router $router */
    $router = \Drupal::service('router.no_access_checks');
    try {
      $page_parameters = $router->match($uri);
    }
    catch (\Exception $e) {
      return NULL;
    }

    // Map expected views arguments (keys) to their respective expected page
    // arguments (values)
    $supported_parameter_map = [
      'api_id' => [
        'plan_id',
        'country_id',
        'donor_id',
        'emergency_id',
      ],
      'usage_year' => ['year'],
      'project_grouping' => ['project_group'],
    ];

    // Iterate over the argument handlers for the view and try to fill them
    // with values.
    $args = [];
    foreach (array_keys($view->argument) as $argument_key) {
      if (empty($supported_parameter_map[$argument_key])) {
        $args[$argument_key] = NULL;
        continue;
      }
      foreach ($supported_parameter_map[$argument_key] as $expected_page_parameter) {
        if (!array_key_exists($expected_page_parameter, $page_parameters)) {
          continue;
        }

        // Add the argument value to the raw arguments passed into the view.
        $args[$argument_key] = $page_parameters[$expected_page_parameter];

        // But also set the argument specifically in the argument handler,
        // otherwise this won't reliably work for PDF titles on data pages that
        // use generic displays, e.g. plan data generic project groups.
        $view->argument[$argument_key]->setArgument($page_parameters[$expected_page_parameter]);
      }
    }
    $view->setArguments(array_values($args));
    $view->initStyle();

    if ($view->getQuery() instanceof HPCDownloadPluginInterface) {
      if (!empty($query['uri'])) {
        // If a uri is given as part of the query, use that.
        $uri = Url::fromUserInput($query['uri'])->toString();
      }
      else {
        // Append the query param to the uri.
        $uri = Url::fromUserInput($uri, [
          'query' => $query,
        ])->toString();
      }
      $view->getQuery()->setCurrentUri($uri);
      $view->getQuery()->setupQuery();
      $view->initPager();
    }

    return $view;
  }

  /**
   * Execute a views query without rendering the view.
   *
   * What's important here, is that the views hooks are still fired that would
   * be fired when preRender, render or preview would have been called.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The views object.
   */
  public static function softExecuteViewsQuery(ViewExecutable $view) {
    // Let modules modify the view just prior to executing it.
    \Drupal::moduleHandler()->invokeAll('views_pre_view', [
      $view,
      $view->current_display,
      &$view->args,
    ]);

    // Execute the query.
    $view->getQuery()->execute($view);

    // Let modules modify the view just after executing it.
    \Drupal::moduleHandler()->invokeAll('views_post_execute', [$view]);
  }

  /**
   * Get default options that each views field should have.
   *
   * Not beautiful, but I wasn't able to retrieve this in another way, so I
   * copied this over from
   * Drupal\views\Plugin\views\field\FieldPluginBase::defineOptions.
   *
   * @return array
   *   An array of default options for a views field.
   */
  public static function defaultFieldOptions() {
    $options = [];
    $options['exclude'] = FALSE;
    $options['alter'] = [
      'alter_text' => FALSE,
      'text' => '',
      'make_link' => FALSE,
      'path' => '',
      'absolute' => FALSE,
      'external' => FALSE,
      'replace_spaces' => FALSE,
      'path_case' => 'none',
      'trim_whitespace' => FALSE,
      'alt' => '',
      'rel' => '',
      'link_class' => '',
      'prefix' => '',
      'suffix' => '',
      'target' => '',
      'nl2br' => FALSE,
      'max_length' => 0,
      'word_boundary' => TRUE,
      'ellipsis' => TRUE,
      'more_link' => FALSE,
      'more_link_text' => '',
      'more_link_path' => '',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'preserve_tags' => '',
      'html' => FALSE,
    ];
    $options['element_type'] = '';
    $options['element_class'] = '';
    $options['element_label_type'] = '';
    $options['element_label_class'] = '';
    $options['element_label_colon'] = TRUE;
    $options['element_wrapper_type'] = '';
    $options['element_wrapper_class'] = '';
    $options['element_default_classes'] = TRUE;
    $options['empty'] = '';
    $options['hide_empty'] = FALSE;
    $options['empty_zero'] = FALSE;
    $options['hide_alter_empty'] = TRUE;
    return $options;
  }

  /**
   * Add and initialize a field handler on the given view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A views executable object.
   * @param string $field_id
   *   The field id for the field to add.
   */
  public static function addFieldHandler(ViewExecutable $view, $field_id) {
    if (!$view->display_handler) {
      \Drupal::logger('HPC Common')->error('Views display handler not initialized.');
      return;
    }
    $fields = $view->display_handler->getOption('fields');
    $handler = Views::handlerManager('field')->getHandler($fields[$field_id]);
    $handler->init($view, $view->display_handler, $fields[$field_id]);
    $view->field[$field_id] = $handler;
  }

}
