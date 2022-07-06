<?php

namespace Drupal\hpc_downloads\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Render\Renderer;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_common\Helpers\ViewsHelper;
use Drupal\hpc_common\Helpers\RequestHelper;
use Drupal\hpc_downloads\DownloadRecord;
use Drupal\hpc_downloads\DownloadSource\ViewsSource;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\Interfaces\HPCBatchedDownloadExcelInterface;
use Drupal\hpc_downloads\Ajax\DownloadObserverCommand;
use Drupal\hpc_downloads\Ajax\DownloadStatusUpdateCommand;
use Drupal\hpc_downloads\DownloadDialog\DownloadDialogPlugin;
use Drupal\hpc_downloads\DownloadDialog\DownloadDialogViews;
use Drupal\hpc_downloads\Interfaces\HPCDownloadSourceInterface;

/**
 * Download controller class.
 */
class DownloadController extends ControllerBase {

  /**
   * The download dialog for generic plugins.
   *
   * @var \Drupal\hpc_downloads\DownloadDialog\DownloadDialogPlugin
   */
  private $pluginDialog;

  /**
   * The download dialog for views.
   *
   * @var \Drupal\hpc_downloads\DownloadDialog\DownloadDialogViews
   */
  private $viewsDialog;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  private $renderer;

  /**
   * Public constructor.
   */
  public function __construct(DownloadDialogPlugin $plugin_dialog, DownloadDialogViews $views_dialog, Connection $database, Renderer $renderer) {
    $this->pluginDialog = $plugin_dialog;
    $this->viewsDialog = $views_dialog;
    $this->database = $database;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hpc_downloads.download_dialog_plugin'),
      $container->get('hpc_downloads.download_dialog_views'),
      $container->get('database'),
      $container->get('renderer')
    );
  }

  /**
   * Show a download dialog.
   */
  public function showDialog($download_source_type, Request $request) {
    $download_source = NULL;
    $plugin = $this->getPluginFromRequest($download_source_type);
    if ($plugin instanceof HPCDownloadPluginInterface) {
      $download_source = $plugin->getDownloadSource();
    }
    elseif ($download_source_type == 'views_executable') {
      // The only other download source besides HPCDownloadPlugins that we
      // support are views executables, e.g. for the IATI plan code list view.
      $view_id = $request->query->get('view_id');
      $view_display = $request->query->get('view_display');
      $view = Views ::getView($view_id);
      $view->setDisplay($view_display);
      $download_source = new ViewsSource($view);
    }

    if (!$download_source) {
      throw new NotFoundHttpException();
    }

    $dialog_options = $request->request->get('dialogOptions', []);
    $title = !empty($dialog_options['title']) ? $dialog_options['title'] : $this->t('Data download');
    unset($dialog_options['title']);

    $build = $download_source->buildDialog();
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new OpenModalDialogCommand($title, $build, $dialog_options));
    return $ajax_response;
  }

  /**
   * Start a download process.
   *
   * 1. Load the block instance for which a download should be created.
   * 2. Create a record for this download in the database.
   * 3. Prepare some options, e.g. filename and path.
   * 4. Update the record with the new options.
   * 5. Hand off to the appropriate download handler.
   * 6. Send record id and message to the client as AJAX response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AjaxResponse object respresenting to update the download modal.
   */
  public function initiate($download_source_type, $download_type, Request $request) {
    $plugin = $this->getPluginFromRequest($download_source_type);
    if (!$plugin) {
      return $this->updateDialogContent($this->t('There was an error initiating the download.'), NULL, 'error');
    }
    // Set the download source.
    if ($plugin instanceof HPCDownloadPluginInterface) {

      $download_source = $plugin->getDownloadSource();
      if (!$download_source->setDownloadMethod($download_type)) {
        return $this->updateDialogContent($this->t('There was an error initiating the download. No suitable download method found for type @type', ['@type' => $download_type]), NULL, 'error');
      }
      $download_dialog = $this->pluginDialog;
    }
    elseif ($download_source_type == 'views_executable') {

      // The only other download source besides HPCDownloadPlugins that we
      // support are views executables.
      $view_id = $request->query->get('view_id');
      $view_display = $request->query->get('view_display');
      $view = Views ::getView($view_id);
      $view->setDisplay($view_display);
      if ($request->query->get('query')) {
        $view->setExposedInput($request->query->get('query'));
      }
      $download_source = new ViewsSource($view);
      $download_dialog = $this->viewsDialog;
    }
    // Prepare options.
    $options = [
      'download_source' => $download_source_type,
      'type' => $download_type,
    ] + $download_source->getDialogOptions();

    // Prepare PDF caption and title.
    if ($this->isPdfDownload($plugin, $download_type)) {
      $options += [
        'caption' => $plugin->getDownloadPdfCaption(),
        'title' => $plugin->getDownloadPdfTitle(),
      ];
    }
    $uri = Url::fromUserInput($options['uri'])->toString();
    // Append additional query args to the uri. This is for when the facet
    // filters are applied or table the sorted.
    if (!empty($options['query'])) {
      // Filter the query to get only the values that are related to facet
      // filter and table sorting.
      $query = array_filter($options['query'], function ($key) {
        return in_array($key, ['f', 'order', 'sort']) ? TRUE : FALSE;
      }, ARRAY_FILTER_USE_KEY);

      $uri = Url::fromUserInput($options['uri'], [
        'query' => $query,
      ])->toString();
    }
    $options['uri'] = $uri;
    $record = DownloadRecord::createRecord($uri, $options);
    if (empty($record) || empty($record['id'])) {
      return $this->updateDialogContent($this->t('There was an error initiating the download.'), NULL, 'error');
    }
    // Prepare additional options and start the Excel export.
    $file_name = $download_source->getDownloadFileName($download_type);
    $file_path = HPCDownloadPluginInterface::DOWNLOAD_DIR . '/' . $options['type'] . '/' . $file_name . '.' . $options['type'];
    $record['options']['file_name'] = $file_name;
    $record['options']['file_path'] = $file_path;
    DownloadRecord::updateRecord($record);
    $commands = [
      new DownloadObserverCommand($record['id']),
    ];

    if ($this->isBatchedExcelDownload($download_source, $download_type)) {

      $record['status'] = DownloadRecord::STATUS_PENDING;
      DownloadRecord::updateRecord($record);

      // Batched download.
      $batch = $download_source->initBatch($record);

      batch_set($batch);
      batch_process(NULL);
      $batch = batch_get();

      $request = new Request([
        'id' => $batch['id'],
        'op' => 'start',
      ]);

      include_once DRUPAL_ROOT . '/core/includes/batch.inc';
      $response = _batch_page($request);

      // We deliberately unset the html_head attributes, that would normally
      // trigger the refresh calls, as well as the library "core/drupal.batch".
      unset($response['content']['#attached']['html_head']);
      unset($response['content']['#attached']['library']);
      $response['content']['#attached']['library'][] = 'core/drupal.progress';

      $response['content']['#theme'] = 'hpc_download_batch_progress_bar';
      $progress_bar = $this->renderer->render($response);

      // Return the rendered progressbar.
      $commands[] = new HtmlCommand('.hpc-download-dialog-content', $progress_bar);
      $commands[] = new HtmlCommand('.hpc-download-dialog-footer', '');
      $commands[] = new InvokeCommand('.hpc-download-dialog-footer', 'addClass', ['hidden']);

    }
    else {
      // Downloads the file and returns its status.
      try {
        $status = $this->createDownloadFile($plugin, $record, $options, $download_source, $download_type);
      }
      catch (\TypeError $e) {
        $this->getLogger('hpc_downloads')->error('Download error: @message in @file:@line', [
          '@message' => $e->getMessage(),
          '@file' => $e->getFile(),
          '@line' => $e->getLine(),
        ]);
        $status = FALSE;
      }
      if ($status) {
        $commands[] = new HtmlCommand('.hpc-download-dialog-footer', $this->t('Download in progress ...'));
      }
      else {
        DownloadRecord::closeRecord($record, DownloadRecord::STATUS_ERROR);
        $link = $download_dialog->buildDialogLink($plugin, $this->t('Back to download options'));
        return $this->updateDialogContent($this->t('There was an error creating the download. Please try again, or contact our team if the problem persists.'), $link, 'error');
      }
    }
    $ajax_response = new AjaxResponse();
    foreach ($commands as $command) {
      $ajax_response->addCommand($command);
    }
    return $ajax_response;
  }

  /**
   * Check the status of a download process.
   *
   * @param int $id
   *   The id of the download record to check.
   */
  public function check($id) {
    $record = DownloadRecord::loadRecordById((int) $id);
    if (!$record) {
      return new JsonResponse(['status' => 'not_found']);
    }

    if ($record['status'] == DownloadRecord::STATUS_SUCCESS) {
      return new JsonResponse(['status' => 'success']);
    }
    elseif ($record['status'] == DownloadRecord::STATUS_ERROR) {
      return new JsonResponse(['status' => 'error']);
    }

    return new JsonResponse(['status' => 'pending']);
  }

  /**
   * Abort a download process.
   *
   * @param int $id
   *   The id of the download record to abort.
   */
  public function abort($id) {
    $record = DownloadRecord::loadRecordById((int) $id);
    if (!$record) {
      return new JsonResponse(['status' => 'not_found']);
    }

    // Mark the download as aborted.
    $record['status'] = DownloadRecord::STATUS_ABORTED;
    DownloadRecord::updateRecord($record);

    $options = $record['options'];

    // Need to terminate the batch process as well.
    if (!empty($options['batch_id'])) {
      $this->database->delete('batch')
        ->condition('bid', $options['batch_id'])
        ->execute();
    }
    return new JsonResponse(['status' => 'aborted']);
  }

  /**
   * Trigger a file download for the given id.
   *
   * @param int $id
   *   The id of the download record.
   */
  public function download($id) {
    $record = DownloadRecord::loadRecordById((int) $id);
    if (!$record) {
      return new JsonResponse(['status' => 'not_found']);
    }
    $file_path = $record['file_path'];
    if (is_file($file_path)) {
      $headers = [
        'Content-Type'              => 'force-download',
        'Content-Disposition'       => 'attachment; filename="' . basename($file_path) . '"',
        'Content-Transfer-Encoding' => 'binary',
        'Pragma'                    => 'no-cache',
        'Cache-Control'             => 'must-revalidate, post-check=0, pre-check=0',
        'Expires'                   => '0',
        'Accept-Ranges'             => 'bytes',
        'Content-Length'            => filesize($file_path),
      ];

      // Trigger the file transfer.
      return new BinaryFileResponse($file_path, 200, $headers);
    }
    return new JsonResponse(['status' => 'not_found']);
  }

  /**
   * Get a download plugin from the current request or optional argumennts.
   *
   * @param string $download_source_type
   *   The type of download source, either block, views_executable or
   *   views_query_batched.
   * @param array $arguments
   *   An optional arguments array, overrideing the query arguments.
   *
   * @return object
   *   The fully instantiated handler for the download source.
   */
  private function getPluginFromRequest($download_source_type, array $arguments = []) {
    $plugin = NULL;

    switch ($download_source_type) {
      case 'block':
        $uri = RequestHelper::getQueryArgument('uri', $arguments);
        $plugin_id = RequestHelper::getQueryArgument('plugin_id', $arguments);
        $block_uuid = RequestHelper::getQueryArgument('block_uuid', $arguments);

        $plugin = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
        break;

      case 'views_executable':
        $uri = RequestHelper::getQueryArgument('uri', $arguments);
        $query = !empty(RequestHelper::getQueryArgument('query', $arguments)) ? RequestHelper::getQueryArgument('query', $arguments) : [];
        $view_id = RequestHelper::getQueryArgument('view_id', $arguments);
        $view_display = RequestHelper::getQueryArgument('view_display', $arguments);

        $plugin = ViewsHelper::getViewInstance($uri, $view_id, $view_display, $query);
        break;

      case 'views_query_batched':
        $uri = RequestHelper::getQueryArgument('uri', $arguments);
        $query = !empty(RequestHelper::getQueryArgument('query', $arguments)) ? RequestHelper::getQueryArgument('query', $arguments) : [];
        $view_id = RequestHelper::getQueryArgument('view_id', $arguments);
        $view_display = RequestHelper::getQueryArgument('view_display', $arguments);

        $view = ViewsHelper::getViewInstance($uri, $view_id, $view_display, $query);
        $plugin = $view->query;
        break;

    }
    return $plugin;
  }

  /**
   * Update the modal dialog.
   */
  private function updateDialogContent($content, $footer_link = NULL, $download_status = NULL) {
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new HtmlCommand('.hpc-download-dialog-content', '<p>' . $content . '</p>'));
    if ($footer_link) {
      $ajax_response->addCommand(new HtmlCommand('.hpc-download-dialog-footer', $footer_link));
      $ajax_response->addCommand(new InvokeCommand('.hpc-download-dialog-footer', 'removeClass', ['hidden']));
    }
    else {
      $ajax_response->addCommand(new HtmlCommand('.hpc-download-dialog-footer', ''));
      $ajax_response->addCommand(new InvokeCommand('.hpc-download-dialog-footer', 'addClass', ['hidden']));
    }
    if ($download_status !== NULL) {
      $ajax_response->addCommand((new DownloadStatusUpdateCommand($download_status)));
    }
    return $ajax_response;
  }

  /**
   * Download the file and get its status.
   */
  private function createDownloadFile($plugin, $record, $options, HPCDownloadSourceInterface $download_source, $download_type) {
    $status = FALSE;
    $download_method = $download_source->getDownloadMethod();
    if ($options['download_source'] == 'views_executable' || $this->isExcelDownload($plugin, $download_type)) {
      // Excel download.
      $data = $download_source->getData();
      $meta_data = $download_source->getMetaData();
      $status = $download_method::createDownloadFileFromSingleTable($record, $data, $meta_data);
    }
    else {
      // PDF or PNG download.
      $status = $download_method::createDownloadFile($record, $options);
    }
    return $status;
  }

  /**
   * Check if download method is PDF.
   */
  private function isPdfDownload($plugin, $download_type) {
    return $plugin instanceof HPCDownloadPDFInterface && $download_type == HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF;
  }

  /**
   * Check if download method is Excel.
   */
  private function isExcelDownload($plugin, $download_type) {
    return $plugin instanceof HPCDownloadExcelInterface && in_array($download_type, [
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLS,
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLSX,
    ]);
  }

  /**
   * Check if download method is Excel.
   */
  private function isBatchedExcelDownload($download_source, $download_type) {
    return $download_source instanceof HPCBatchedDownloadExcelInterface && in_array($download_type, [
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLS,
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLSX,
    ]);
  }

}
