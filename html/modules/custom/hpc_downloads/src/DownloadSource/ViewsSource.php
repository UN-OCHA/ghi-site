<?php

namespace Drupal\hpc_downloads\DownloadSource;

use Drupal\views\ViewExecutable;
use Drupal\Component\Utility\UrlHelper;

use Drupal\hpc_common\Helpers\RequestHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;

/**
 * A download source class for views executables.
 *
 * Used to provide downloads for standard views built in Drupal.
 */
class ViewsSource extends DownloadSourceBase {

  /**
   * The views executable.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * Instantiate the class.
   */
  public function __construct(ViewExecutable $view) {
    $this->view = $view;
  }

  /**
   * Get the download type to use.
   *
   * @see DownloadController::getPluginFromRequest()
   */
  public function getType() {
    return 'views_executable';
  }

  /**
   * Get the options for the download dialog.
   */
  public function getDialogOptions() {
    return [
      'uri' => RequestHelper::getQueryArgument('uri') ? RequestHelper::getQueryArgument('uri') : \Drupal::service('path.current')->getPath(),
      'query' => RequestHelper::getQueryArgument('query') ? RequestHelper::getQueryArgument('query') : \Drupal::request()->query->all(),
      'view_id' => $this->view->id(),
      'view_display' => $this->view->current_display,
    ];
  }

  /**
   * Get the plugin to use for retrieving the download data.
   */
  public function getPlugin() {
    return $this->view;
  }

  /**
   * Get download method.
   */
  public function getDownloadMethod() {
    return '\Drupal\hpc_downloads\DownloadMethods\Excel';
  }

  /**
   * Build the download dialog.
   */
  public function buildDialog() {
    $dialog_service = \Drupal::service('hpc_downloads.download_dialog_views');
    $options = $this->getDialogOptions();

    $links = [];
    // Plain views support only download as Excel.
    $available_download_types = [
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLSX => $this->t('Download data (XLSX)'),
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLS => $this->t('Download data (XLS)'),
    ];
    foreach ($available_download_types as $download_type => $label) {
      $links[] = $dialog_service->buildDownloadLink($this, $download_type, $label, $options);
    }

    $build = [
      '#theme' => 'hpc_download_dialog',
      '#content' => [
        '#type' => 'container',
        '#children' => $links,
      ],
      '#attached' => ['libraries' => ['hpc_downloads']],
    ];
    return !empty($links) ? $build : NULL;
  }

  /**
   * Get download file name.
   */
  public function getDownloadFileName($type) {
    $title = $this->view->getTitle();
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
   * Fetch the actual download data.
   */
  public function getData() {
    $view = $this->getPlugin();
    $view->is_download = TRUE;
    $view->executed = FALSE;
    $view->live_preview = TRUE;
    $view->setItemsPerPage(0);

    // Execute and render the view, so that all fields all rendered pretty much
    // the same as in the website, only some slight download specific
    // adjustments, mainly on number formatting.
    $view->executeDisplay($view->current_display, $view->args);

    $fields = $view->display_handler->getOption('fields');

    $header = array_values(array_map(function ($item) {
      return $item['label'];
    }, $fields));

    $rows = [];

    // Go over the rendered fields array and create the table rows.
    foreach ($view->result as $index => $result_row) {
      $row = [];
      foreach ($fields as $field_key => $field) {
        $field_value = (string) $view->style_plugin->getField($index, $field_key);
        if (strpos($field_value, 'multivalue-item')) {
          $row[$field_key] = [
            'data' => trim(strip_tags($field_value)),
            'class' => 'multivalue-item',
          ];
        }
        else {
          $row[$field_key] = trim(strip_tags($field_value));
        }
      }
      $rows[] = array_values($row);
    }

    return ['header' => $header, 'rows' => $rows];
  }

  /**
   * Fetch the meta data for a download.
   */
  public function getMetaData() {
    $view = $this->getPlugin();
    $meta_data = [
      [$this->t('Export of'), $view->getTitle()],
      [$this->t('Date'), date('d/m/Y H:i')],
    ];

    // Get the exposed input, note that this also includes query arguments.
    $exposed_form = $view->display_handler->getPlugin('exposed_form')->renderExposedForm();
    $fields = $view->display_handler->getOption('fields');
    $filters = array_filter($view->getExposedInput(), function ($value, $key) use ($exposed_form, $fields) {
      return !empty($exposed_form[$key]) && !empty($fields[$key]) &&!empty($value) && $value != 'All';
    }, ARRAY_FILTER_USE_BOTH);

    if (!empty($filters)) {
      foreach ($filters as $filter_key => $filter_value) {
        $display_value = !empty($exposed_form[$filter_key]['#options']) && !empty($exposed_form[$filter_key]['#options'][$filter_value]) ? $exposed_form[$filter_key]['#options'][$filter_value] : $filter_value;
        $meta_data[] = [
          $this->t('Filtered by @filter_name', ['@filter_name' => strtolower((string) $fields[$filter_key]['label'])]),
          $display_value,
        ];
      }
    }

    $meta_data[] = [
      $this->t('Results'),
      count($view->result),
    ];
    return $meta_data;
  }

}
