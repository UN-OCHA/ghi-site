<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for listing all supported endpoints.
 */
class EndpointReportController extends ControllerBase {

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  protected $endpointQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\hpc_api\Controller\EndpointReportController $instance */
    $instance = new static();
    $instance->endpointQueryManager = $container->get('plugin.manager.endpoint_query_manager');
    return $instance;
  }

  /**
   * Build a list with all endpoints supported by this site.
   */
  public function buildEndpointList() {
    $definitions = $this->endpointQueryManager->getDefinitions();
    $rows = [];

    foreach ($definitions as $key => $definition) {
      $query = array_map(function ($key, $value) {
        return $key . '=' . $value;
      }, array_keys($definition['endpoint']['query'] ?? []), $definition['endpoint']['query'] ?? []);
      $version = $definition['endpoint']['version'];
      $query_string = !empty($query) ? ('?' . implode('&', $query)) : '';

      $endpoint_public = $definition['endpoint']['public'] ?? NULL;
      $endpoint_authenticated = $definition['endpoint']['authenticated'] ?? NULL;
      $endpoint_backend = $definition['endpoint']['api_key'] ?? NULL;

      $row = [
        $definition['label'],
        [
          'data' => [
            '#markup' => Markup::create(implode('<br />', array_filter([
              $endpoint_public ? ('/' . $version . '/' . $endpoint_public . $query_string) : NULL,
              $endpoint_authenticated ? ('/' . $version . '/' . $endpoint_authenticated . $query_string) : NULL,
              $endpoint_backend ? ('/' . $version . '/' . $endpoint_backend . $query_string) : NULL,
            ]))),
          ],
        ],
      ];
      $rows[$key] = $row;
    }

    return [
      '#type' => 'container',
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This page lists all API endpoints used by this site. Please note that the shown query strings are not necesarily exhaustive, as individual page elements which are using an endpoint can add parameters to the query.'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Query'),
          $this->t('Endpoints'),
        ],
        '#rows' => $rows,
      ],
    ];
  }

}
