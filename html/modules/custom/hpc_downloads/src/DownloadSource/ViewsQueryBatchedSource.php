<?php

namespace Drupal\hpc_downloads\DownloadSource;

use Drupal\Component\Utility\Html;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

use Drupal\hpc_common\Helpers\RequestHelper;
use Drupal\hpc_common\Helpers\ViewsHelper;
use Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\hpc_downloads\DownloadRecord;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadViewsQueryInterface;

/**
 * A download source class for batched views queries.
 */
class ViewsQueryBatchedSource extends DownloadSourceBase implements HPCBatchedDownloadExcelInterface {

  /**
   * The tmp store array.
   *
   * @var array
   */
  protected $tmpStore;

  /**
   * Get the download type to use.
   *
   * @see DownnloadController::getPluginFromRequest()
   */
  public function getType() {
    return 'views_query_batched';
  }

  /**
   * Get the options for the download dialog.
   */
  public function getDialogOptions() {
    return [
      'uri' => RequestHelper::getQueryArgument('uri') ? RequestHelper::getQueryArgument('uri') : \Drupal::service('path.current')->getPath(),
      'query' => RequestHelper::getQueryArgument('query') ? RequestHelper::getQueryArgument('query') : \Drupal::request()->query->all(),
      'view_id' => $this->plugin->view->id(),
      'view_display' => $this->plugin->view->current_display,
    ];
  }

  /**
   * Build the download dialog.
   */
  public function buildDialog() {
    $dialog_service = \Drupal::service('hpc_downloads.download_dialog_plugin');
    $options = $this->getDialogOptions();

    $links = [];
    $available_download_types = $this->plugin->getAvailableDownloadTypes();
    foreach ($available_download_types as $download_type => $label) {
      $links[] = $dialog_service->buildDownloadLink($this, $download_type, $label, $options);
    }

    if (!empty($links)) {
      $links[] = [
        '#markup' => Markup::create($this->t('Please note that generating downloads from large data-sets may take a few moments.')),
      ];
    }
    $build = [
      '#theme' => 'hpc_download_dialog',
      '#content' => [
        '#type' => 'container',
        '#children' => $links,
      ],
      '#attached' => ['libraries' => ['hpc_downloads']],
      '#attributes' => ['class' => [Html::getClass($this->getType())]],
    ];
    return !empty($links) ? $build : NULL;
  }

  /**
   * Retrieve the data for a download.
   *
   * @return array
   *   Array that could be used in theme_table().
   */
  public function getData() {
    $data = $this->plugin->buildDownloadData($this->tmpStore);
    return Excel::renderTableArray($data);
  }

  /**
   * Fetch the meta data for a download.
   */
  public function getMetaData() {
    $meta_data = $this->plugin->buildMetaData();
    return Excel::renderTableArray($meta_data);
  }

  /**
   * Get download file name.
   */
  public function getDownloadFileName($type) {
    if ($this->plugin instanceof HPCDownloadViewsQueryInterface && method_exists($this->plugin, 'getDownloadFileName')) {
      $title = $this->plugin->getDownloadFileName();
    }
    else {
      $title = $this->plugin instanceof HPCDownloadPDFInterface && $type == HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF ?
        $this->plugin->getDownloadPdfCaption() . '_' . $this->plugin->view->current_display :
        $this->plugin->getDownloadCaption() . '_' . $this->plugin->view->current_display;
    }
    $title .= '_' . md5(UrlHelper::buildQuery($this->getDialogOptions()));
    $title .= '_as_on_' . date('Y-m-d');
    // Replace any / any html tags that would interfere with the filepath in the
    // filename.
    $title = strip_tags(str_replace(['/', ':', '%', ' '], '_', $title));
    // Remove anything which isn't a word, whitespace, number or any of the
    // following caracters -_~,;[]().
    $title = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title);
    return 'FTS_' . $title;
  }

  /**
   * Initialize the batch.
   */
  public function initBatch(array $record) {
    $operation_args = [$this, $record];
    $operations = $this->getOperations($record['options'], $record);
    $operations[] = [
      '\Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource::prepareDataBatch',
      $operation_args,
    ];
    $operations[] = [
      '\Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource::segmentBatch',
      $operation_args,
    ];
    $operations[] = [
      '\Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource::writeBatch',
      $operation_args,
    ];
    $batch = [
      'operations' => $operations,
      'title' => $this->t('Processing download'),
      'init_message' => $this->t('Preparing download ...'),
      'progress_message' => $this->t('Preparing next steps ...'),
      'error_message' => $this->t('Download has encountered an error.'),
      'finished' => '\Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource::finished',
    ];
    return $batch;
  }

  /**
   * Get the batch operations.
   */
  public function getOperations(array $options, $download_record) {
    if (method_exists($this->plugin, 'getBatchOperations')) {
      return $this->plugin->getBatchOperations($options, $download_record, $this);
    }
    return [
      [
        '\Drupal\hpc_downloads\DownloadSource\ViewsQueryBatchedSource::processBatch',
        [$this, $options, $download_record],
      ],
    ];
  }

  /**
   * Retrieve the maximum amount of pages.
   */
  public function getMaxPages() {
    $max_pages = 1;
    if (!empty($this->plugin->options['group'])) {
      // Grouped views don't have a pager.
      return $max_pages;
    }
    $total_items = $this->plugin->view->total_rows;
    $limit = $this->getLimit();
    if ($total_items > 1 && $limit > 0) {
      $max_pages = ceil($total_items / $limit);
    }
    return $max_pages;
  }

  /**
   * Retrieve the limit to use for batched queries.
   *
   * @return int
   *   The limit as int.
   */
  public function getLimit() {
    if (!empty($this->plugin->options['group'])) {
      // Grouped views don't have a limit.
      return HPCDownloadPluginInterface::CUSTOM_SEARCH_MAX_LIMIT;
    }
    $total_items = $this->plugin->view->total_rows;
    if ($total_items > 250 && $total_items / HPCDownloadPluginInterface::CUSTOM_SEARCH_MAX_LIMIT < 3) {
      return ceil($total_items / 3);
    }
    return HPCDownloadPluginInterface::CUSTOM_SEARCH_MAX_LIMIT;
  }

  /**
   * Initialize the plugin.
   *
   * @param array $options
   *   The options array.
   * @param int $page
   *   The page of the batched download.
   * @param bool $execute
   *   Flag to indicate whether the view query should be executed.
   */
  public function initPlugin(array $options, $page = NULL, $execute = FALSE) {
    if (!empty($options['view_display'])) {
      $this->plugin->view->setDisplay($options['view_display']);
    }

    // We reload the view to be sure to have a fresh instance. Views is
    // complicated and quite difficult to debug, things happen all over the
    // place and especially with our pretty advanced tinkering of the fields
    // for the column select feature we have run into all sorts of difficulties
    // with handlers being cached, overridden states not being recognized.
    // Might also have to do with the fact that the views object is serialized
    // between batch requests.
    $uri = $this->plugin->getCurrentUri();
    $view_id = $this->plugin->view->id();
    $view_display = $this->plugin->view->current_display;
    $query = $this->plugin->getUnprocessedQueryArguments();
    $view = ViewsHelper::getViewInstance($uri, $view_id, $view_display, $query);
    $view->is_download = TRUE;

    if ($execute) {
      $view->query->execute($view);
    }
    $view->executed = FALSE;
    $view->download_options = $options;

    // And pretend that's the views object we had all the time.
    $this->plugin->view = $view;
  }

  /**
   * Fetch data for the given page in the result set.
   *
   * @param int $page
   *   The page of the batched download.
   * @param int $limit
   *   The count of items per page.
   */
  public function fetchData($page, $limit = NULL) {

    // Now set the page and some flags.
    $this->plugin->view->setCurrentPage($page - 1);
    $this->plugin->view->setItemsPerPage($limit ? $limit : HPCDownloadPluginInterface::CUSTOM_SEARCH_MAX_LIMIT);

    // Do a soft execute so that we don't have to wait for all the field
    // handlers to render.
    ViewsHelper::softExecuteViewsQuery($this->plugin->view);

    // Process the data and add it to the tmp store.
    $this->tmpStore = $this->plugin->processDownloadData($this->tmpStore, '\Drupal\hpc_downloads\DownloadMethods\Excel::renderValue');

    return TRUE;
  }

  /**
   * Process batch operation callback.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface $handler
   *   The download handler.
   * @param array $options
   *   The options array.
   * @param array $download_record
   *   A download record array.
   * @param array $context
   *   The batch contexts.
   */
  public static function processBatch(HPCBatchedDownloadExcelInterface $handler, array $options, array $download_record, array &$context) {

    // Reload to capture updates in between runs.
    $download_record = DownloadRecord::loadRecordById($download_record['id']);

    if (!isset($context['sandbox']['progress'])) {
      // For multi-operation batch process, we keep the tmp store in the
      // context array that is reliably shared between the different batch
      // operations.
      if (!empty($context['results']['tmp_store'])) {
        $handler->tmpStore = unserialize(gzuncompress($context['results']['tmp_store']));
      }
      $handler->initPlugin($options, NULL, TRUE);

      $download_record['options']['batch_id'] = batch_get()['id'];
      DownloadRecord::updateRecord($download_record);

      $context['results']['tmp_store'] = gzcompress(serialize($handler->tmpStore), 6);
      $context['sandbox']['download_record'] = $download_record;
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['limit'] = $handler->getLimit();
      $context['sandbox']['max'] = $handler->getMaxPages($context['sandbox']['limit']);

      $context['finished'] = 0;
      return;
    }

    // The page to fetch data for in this run.
    $page = $context['sandbox']['progress'] + 1;
    $handler->initPlugin($options, $page);

    // Fetch the data for this run.
    $handler->tmpStore = unserialize(gzuncompress($context['results']['tmp_store']));
    $handler->fetchData($page, $context['sandbox']['limit']);
    DownloadRecord::updateRecord($download_record);

    // Update our progress information.
    $context['sandbox']['progress']++;
    $context['results']['tmp_store'] = gzcompress(serialize($handler->tmpStore), 6);

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      $message = (string) t('Fetching data ... (@current/@max)', [
        '@current' => $context['sandbox']['progress'],
        '@max' => $context['sandbox']['max'],
      ]);
      $context['message'] = $message;
      $context['init_message'] = $message;
    }
    else {
      if (method_exists($handler->plugin, 'downloadDataBatchFinished')) {
        $handler->tmpStore = $handler->plugin->downloadDataBatchFinished($download_record['options'], $handler->tmpStore);
      }

      $context['results']['data'] = gzcompress(serialize($handler->getData($options)));
      $context['results']['download_record'] = $download_record;
    }
  }

  /**
   * Process batch operation callback.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface $handler
   *   The download handler.
   * @param array $download_record
   *   A download record array.
   * @param array $context
   *   The batch contexts.
   */
  public static function prepareDataBatch(HPCBatchedDownloadExcelInterface $handler, array $download_record, array &$context) {

    // Get the data.
    $data = unserialize(gzuncompress($context['results']['data']));

    if (!isset($context['sandbox']['sheet_keys'])) {
      // Prepare the sandbox.
      $context['sandbox']['sheet_keys'] = array_keys($data['header']);
      $context['sandbox']['sheets'] = [];
      $context['sandbox']['max'] = count($context['sandbox']['sheet_keys']);

      // Free some ressources already.
      unset($context['results']['tmp_store']);
    }

    // Not ideal, this is one sheet per batch, which can take very long.
    $sheet_key = array_shift($context['sandbox']['sheet_keys']);
    $build = Excel::prepareExcelSheet($data['header'][$sheet_key], $data['rows'][$sheet_key]);
    $context['sandbox']['sheets'][$sheet_key] = gzcompress(serialize($build), 6);

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if (!empty($context['sandbox']['sheet_keys'])) {
      // Still ongoing.
      $context['finished'] = count($context['sandbox']['sheets']) / $context['sandbox']['max'];
      $message = (string) t('Preparing data ... (@current/@max)', [
        '@current' => count($context['sandbox']['sheets']),
        '@max' => $context['sandbox']['max'],
      ]);
      $context['message'] = $message;
      $context['init_message'] = $message;
    }
    else {
      // Finished.
      $build = [];
      foreach ($context['sandbox']['sheets'] as $sheet_key => $sheet_data_compressed) {
        $sheet_data = unserialize(gzuncompress($sheet_data_compressed));
        foreach ($sheet_data as $build_key => $build_data) {
          $build[$build_key][$sheet_key] = $build_data;
        }
      }

      $context['results']['build'] = gzcompress(serialize($build), 6);

      unset($context['sandbox']['sheets']);
      unset($context['results']['data']);
    }
  }

  /**
   * Process batch operation callback.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface $handler
   *   The download handler.
   * @param array $download_record
   *   A download record array.
   * @param array $context
   *   The batch contexts.
   */
  public static function segmentBatch(HPCBatchedDownloadExcelInterface $handler, array $download_record, array &$context) {
    module_load_include('inc', 'phpexcel');

    // Prepare the rows.
    $limit = \Drupal::config('hpc_downloads.settings')->get('excel_segment_size');

    if (!isset($context['sandbox']['progress'])) {
      $build = unserialize(gzuncompress($context['results']['build']));
      $options = Excel::prepareOptions($download_record, $build);
      if (method_exists($handler->plugin, 'getDownloadColumnFormats')) {
        // Do a soft execute so that we don't have to wait for all the field
        // handlers to render. We still need to run this to make sure that
        // field altering, e.g. for organization subtypes, is correctly
        // applied.
        $handler->initPlugin($options, NULL, TRUE);
        ViewsHelper::softExecuteViewsQuery($handler->plugin->view);
        $column_formats = $handler->plugin->getDownloadColumnFormats($options);
        $options['column_formats'] = $column_formats;
      }

      // Build the template.
      $options['progressive'] = TRUE;
      $options['finished'] = FALSE;
      $temp_file = \Drupal::service('file_system')->realpath($options['temp_name']);

      $initial_data = [];
      foreach ($build['rows'] as $sheet_key => $sheet_data) {
        $initial_data[$sheet_key] = $sheet_key == 'Meta data' ? $sheet_data : [];
      }
      ini_set('memory_limit', '512M');
      phpexcel_export($build['header'], $initial_data, $temp_file, $options);

      // Remove the meta data sheet.
      array_shift($build['rows']);

      // Get the total count to process.
      $total_count = 0;
      foreach ($build['rows'] as $row_data) {
        $total_count += count($row_data);
      }
      $sheet_keys = array_keys($options['header']);

      // Prepare the rest of the sandbox.
      $context['sandbox']['rows'] = gzcompress(serialize($build['rows']), 6);
      $context['sandbox']['sheet_keys'] = $sheet_keys;
      $context['sandbox']['rows_segmented'] = [];
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $limit && $total_count > $limit ? ceil($total_count / $limit) : 1;

      $context['results']['options'] = $options;
      $context['results']['temp_file'] = $temp_file;

      // Free some ressources already.
      unset($context['results']['build']);
    }

    // Get the rows for this run.
    $rows = unserialize(gzuncompress($context['sandbox']['rows']));
    $current_index = $context['sandbox']['progress'];
    $sheet_keys = $context['sandbox']['sheet_keys'];
    $current_sheet = reset($sheet_keys);

    // Go on and segment the result rows into segments of size $limit.
    $rows_segmented = [];
    while (!empty($rows)) {
      while (count($sheet_keys) && !array_key_exists($current_sheet, $rows)) {
        $current_sheet = array_shift($sheet_keys);
      }
      if (!array_key_exists($current_sheet, $rows)) {
        break;
      }
      if (!array_key_exists($current_sheet, $rows_segmented)) {
        $rows_segmented[$current_sheet] = [];
      }
      $rows_segmented[$current_sheet][] = array_shift($rows[$current_sheet]);
      if (empty($rows[$current_sheet])) {
        unset($rows[$current_sheet]);
      }
      if (count($rows_segmented[$current_sheet]) == $limit || empty($rows)) {
        break;
      }
    }

    $context['sandbox']['rows_segmented'][$current_index] = gzcompress(serialize($rows_segmented), 6);
    $context['sandbox']['rows'] = gzcompress(serialize($rows), 6);
    $context['sandbox']['progress']++;

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      // Still ongoing.
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      $message = (string) t('Processing data ... (@current/@max)', [
        '@current' => $context['sandbox']['progress'],
        '@max' => $context['sandbox']['max'],
      ]);
      $context['message'] = $message;
      $context['init_message'] = $message;
    }
    else {
      // Finished.
      $context['results']['rows_segmented'] = $context['sandbox']['rows_segmented'];
      $message = (string) t('Writing data ...');
      $context['message'] = $message;
      $context['init_message'] = $message;
    }
  }

  /**
   * Process batch operation callback.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface $handler
   *   The download handler.
   * @param array $download_record
   *   A download record array.
   * @param array $context
   *   The batch contexts.
   */
  public static function writeBatch(HPCBatchedDownloadExcelInterface $handler, array $download_record, array &$context) {
    module_load_include('inc', 'phpexcel');

    $options = $context['results']['options'];
    $temp_file = $context['results']['temp_file'];
    $sheet_keys = array_keys($options['header']);

    if (!isset($context['sandbox']['max'])) {
      // Prepare the rest of the sandbox.
      $context['sandbox']['rows'] = $context['results']['rows_segmented'];
      $context['sandbox']['max'] = count($context['sandbox']['rows']);

      // Free some ressources already.
      unset($context['results']['rows_segmented']);
    }

    $current_rows = unserialize(gzuncompress(array_shift($context['sandbox']['rows'])));
    foreach ($sheet_keys as $sheet_key => $sheet_name) {
      $sheets[$sheet_name] = $sheet_key == 0 ? [[]] : (!empty($current_rows[$sheet_name]) ? $current_rows[$sheet_name] : [[]]);
    }

    // Get and ammend options.
    $options['progressive'] = TRUE;
    $options['template'] = $temp_file;
    $options['finished'] = empty($context['sandbox']['rows']);

    // Write the current data to the file.
    ini_set('memory_limit', '512M');
    phpexcel_export($options['header'], $sheets, $temp_file, $options);

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if (!empty($context['sandbox']['rows'])) {
      // Still ongoing.
      $context['finished'] = ($context['sandbox']['max'] - count($context['sandbox']['rows'])) / $context['sandbox']['max'];
      $message = (string) t('Writing data ... (@current/@max)', [
        '@current' => $context['sandbox']['max'] - count($context['sandbox']['rows']),
        '@max' => $context['sandbox']['max'],
      ]);
      $context['message'] = $message;
      $context['init_message'] = $message;
    }
    else {
      // Finished.
      $context['results']['download_record'] = $download_record;
    }
  }

  /**
   * Finished callback for a batch download process.
   *
   * @param bool $success
   *   Whether the batch process finished successfully.
   * @param array $results
   *   The results of the batch process.
   * @param array $operations
   *   The operations.
   */
  public static function finished($success, array $results, array $operations) {
    $download_record = $results['download_record'];
    $download_record = DownloadRecord::loadRecordById($download_record['id']);
    $options = $results['options'];
    $status = Excel::finaliseFileGeneration($success, $options, $download_record);
    if ($success && $status) {
      $redirect_url = Url::fromRoute('hpc_downloads.download', ['id' => $download_record['id']])->toString();
      return new RedirectResponse($redirect_url, 302);
    }
    // Otherwhise we abort.
    $redirect_url = Url::fromRoute('hpc_downloads.check', ['id' => $download_record['id']])->toString();
    return new RedirectResponse($redirect_url, 302);
  }

}
