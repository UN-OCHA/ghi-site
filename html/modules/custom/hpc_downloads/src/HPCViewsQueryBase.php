<?php

namespace Drupal\hpc_downloads;

use Drupal\search_api\Plugin\views\query\SearchApiQuery;

use Drupal\hpc_downloads\Interfaces\HPCDownloadViewsQueryInterface;
use Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource;

/**
 * Base class for Views queries.
 */
abstract class HPCViewsQueryBase extends SearchApiQuery implements HPCDownloadViewsQueryInterface {

  /**
   * The page URI of the current views display.
   *
   * @var string
   */
  protected $currentUri;

  /**
   * The endpoint URL used to fetch data for the current views display.
   *
   * @var string
   */
  protected $endpointUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (!$this->getCurrentUri()) {
      $this->setCurrentUri();
    }
  }

  /**
   * Setup the query.
   */
  abstract public function setupQuery();

  /**
   * Get a caption to be used in downloads.
   */
  abstract public function getDownloadCaption();

  /**
   * Get the endpoint function.
   *
   * @return string
   *   The endpoint function used to retrieve the data for a download.
   */
  abstract public function getEndpointFunction();

  /**
   * Get the field list from the view.
   *
   * @return array
   *   An array of fields.
   */
  abstract public function getFieldList();

  /**
   * Process the download data.
   */
  abstract public function processDownloadData($download_data = NULL);

  /**
   * Build the download data.
   */
  abstract public function buildDownloadData($download_data);

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->view->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadSource() {
    return new ViewsQueryBatchedSource($this->view->query);
  }

  /**
   * Set the URI for current page.
   */
  public function setCurrentUri($current_uri = NULL) {
    if ($current_uri === NULL) {
      $current_uri = \Drupal::request()->getRequestUri();
    }
    $this->currentUri = $current_uri;
    // Also add it to the exposed raw input of the view. This is necessary to
    // persist the URI during batch runs. The situation is a bit complicated,
    // but it seems that during the different stages of a batch run, the view
    // object, including this query handler, are getting serialized and stored
    // in temp storage. When the objects are needed on a subsequent stage of
    // the batch, the objects are unserialized again and the view is
    // automatically executed at a stage where we didn't have the chance to
    // recreate our custom context yet, which basically means that we loose the
    // filter information. Adding this to the exposed data raw data property
    // assures that the information is available after the view is woke up.
    // @see HPCApiQuery::getCurrentUri()
    // @see ViewExecutable::__sleep()
    // @see ViewExecutable::__wakeup()
    if (is_object($this->view)) {
      $this->view->exposed_raw_input['source_uri'] = $current_uri;
    }
  }

  /**
   * Get the URI for current page.
   */
  public function getCurrentUri() {
    if (!$this->view) {
      return $this->currentUri;
    }
    return !empty($this->view->exposed_raw_input['source_uri']) ? $this->view->exposed_raw_input['source_uri'] : $this->currentUri;
  }

  /**
   * Log the endpoint URL used for the current view..
   *
   * @param array $args
   *   An array of arguments to pass on to the endpoint.
   */
  public function logEndpointUrl(array $args) {
    $data_callback = $this->getEndpointFunction();

    $endpoint_url = NULL;
    switch ($data_callback) {
      case 'hpc_api_query_custom_search':
        $endpoint_url = 'fts/flow/custom-search';
        break;

      case 'hpc_api_query_projects':
        $endpoint_url = 'fts/project/plan';
        break;
    }

    if (!$endpoint_url) {
      return;
    }

    $query_handler = \Drupal::service('hpc_api.endpoint_query');
    $query_handler->setArguments([
      'endpoint' => $endpoint_url,
      'query_args' => $args,
    ]);
    $this->endpointUrl = $query_handler->getFullEndpointUrl();
  }

  /**
   * Get the endpoint URL used to retieve data.
   */
  public function getEndpointUrl() {
    return $this->endpointUrl;
  }

  /**
   * Get the available options for the items per page selector.
   */
  public function getItemsPerPageOptions() {
    return [25, 50];
  }

}
