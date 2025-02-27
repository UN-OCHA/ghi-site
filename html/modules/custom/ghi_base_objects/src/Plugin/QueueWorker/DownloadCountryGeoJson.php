<?php

namespace Drupal\ghi_base_objects\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Update monitoring period queue Worker.
*
* @QueueWorker(
*   id = "ghi_base_objects_download_country_geojson",
*   title = @Translation("Download GeoJson data for the country outlines"),
*   cron = {"time" = 60}
* )
*/
final class DownloadCountryGeoJson extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The country query plugin.
   *
   * @var \Drupal\ghi_base_objects\Plugin\EndpointQuery\CountryQuery
   */
  protected $countryQuery;

  /**
   * Used to grab functionality from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->countryQuery = $container->get('plugin.manager.endpoint_query_manager')->createInstance('country_query');
    return $instance;
  }

  /**
   * Processes an item in the queue.
   *
   * @param mixed $data
   *   The queue item data.
   */
  public function processItem($data) {
    $country_id = $data->country_id;
    $location = $this->countryQuery->getCountry($country_id);
    $location->getGeoJson();
  }

}
