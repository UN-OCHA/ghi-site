<?php

namespace Drupal\hpc_downloads\DownloadMethods;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;

use Drupal\hpc_downloads\DownloadRecord;
use Drupal\hpc_downloads\Helpers\FileHelper;

/**
 * Create Excel files.
 */
class Excel {

  /**
   * Create a download file from a data and a meta data array.
   *
   * @param array $record
   *   The download record array.
   * @param array $data
   *   The data array.
   * @param array $meta_data
   *   The meta data array.
   *
   * @return bool
   *   Returns TRUE if file generation was successful; otherwise returns FALSE.
   */
  public static function createDownloadFileFromSingleTable(array $record, array $data, array $meta_data) {
    $file_data = self::buildFileData($data, $meta_data);
    return self::createDownloadFile($record, $file_data);
  }

  /**
   * Create a download file from an already prepared array of file data.
   *
   * @param array $record
   *   The download record array.
   * @param array $file_data
   *   The data for the file.
   *
   * @return bool
   *   Returns TRUE if file generation was successful; otherwise returns FALSE.
   */
  public static function createDownloadFile(array $record, array $file_data) {
    $build = self::prepareExcelData($file_data);
    $options = self::prepareOptions($record, $build);
    return self::writeFile($record, $build, $options);
  }

  /**
   * Prepate the data for multiple worksheets / tables for this download build.
   *
   * @param array $build
   *   The fully built table array.
   *
   * @return array
   *   The build array for the final excel file.
   */
  private static function prepareExcelData(array $build) {
    foreach (array_keys($build['header']) as $sheet_key) {
      $_build = self::prepareExcelSheet($build['header'][$sheet_key], $build['rows'][$sheet_key]);
      $build['header'][$sheet_key] = $_build['header'];
      $build['rows'][$sheet_key] = $_build['rows'];
      $build['footnotes'][$sheet_key] = !empty($_build['footnotes']) ? $_build['footnotes'] : [];
      $build['cell_formats'][$sheet_key] = !empty($_build['cell_formats']) ? $_build['cell_formats'] : [];
      $build['cell_align'][$sheet_key] = !empty($_build['cell_align']) ? $_build['cell_align'] : [];
      $build['colspan'][$sheet_key] = !empty($_build['colspan']) ? $_build['colspan'] : [];
    }
    return $build;
  }

  /**
   * Prepate the data for a single worksheet for this download build.
   *
   * @param array $header
   *   The header.
   * @param array $rows
   *   The rows.
   *
   * @return array
   *   The table that would make the excel download.
   */
  public static function prepareExcelSheet(array $header, array $rows) {
    $_build = [
      'header' => $header,
      'rows' => $rows,
    ];
    return self::prepareTableData($_build);
  }

  /**
   * Prepare the table data for this download build.
   *
   * @param array $build
   *   The build array for the final excel file.
   *
   * @return array
   *   The prepared build array for the final excel file.
   */
  private static function prepareTableData(array $build) {
    // Prepare header and rows, render arrays, strip_tags, check_plain.
    $build['header'] = self::renderTableArray($build['header']);
    $build['header'] = array_map(function ($item) {
      return is_array($item) && array_key_exists('data', $item) ? $item['data'] : $item;
    }, $build['header']);

    // Now that was an interesting problem. After adding the previous code
    // block to move group rows into the first column of each row that belongs
    // to the group, the actual building of the row values in the next foreach
    // produced a duplicated row at the end of the table, so that n-1 row was
    // equal to the last row and the actual value of the last row was missing.
    // Unsetting the $column variable here solved the problem, which indicates
    // that the variable referrence is still hold somewhere in memory, even
    // though it's a local variable in the scope of the previous foreach. No
    // idea why this only affected the last row and not any of the others.
    $cells = NULL;
    unset($cells);

    $build['footnotes'] = [];
    foreach ($build['rows'] as $row_key => $cells) {
      if (!is_array($cells)) {
        continue;
      }
      if (array_key_exists('data', $cells)) {
        $cells = $cells['data'];
        $build['rows'][$row_key] = $cells;
      }
      foreach ($cells as $cell_key => $cell) {
        if (is_array($cell) && array_key_exists('export_value', $cell)) {
          // This has been crafted before, so let's use it.
          $build['rows'][$row_key][$cell_key] = $cell['export_value'];
        }
        else {
          $build['rows'][$row_key][$cell_key] = $cell;
        }

        if (is_array($cell) && array_key_exists('export_commentary', $cell) && !empty($cell['export_commentary'])) {
          if (empty($build['footnotes'][$row_key])) {
            $build['footnotes'][$row_key] = [];
          }
          $build['footnotes'][$row_key][$cell_key] = $cell['export_commentary'];
        }
        if (is_array($cell) && !empty($cell['excel_format'])) {
          if (empty($build['cell_formats'][$row_key])) {
            $build['cell_formats'][$row_key] = [];
          }
          $build['cell_formats'][$row_key][$cell_key] = $cell['excel_format'];
        }
        if (is_array($cell) && !empty($cell['excel_align'])) {
          if (empty($build['cell_align'][$row_key])) {
            $build['cell_align'][$row_key] = [];
          }
          $build['cell_align'][$row_key][$cell_key] = $cell['excel_align'];
        }
        if (is_array($cell) && !empty($cell['colspan'])) {
          if (empty($build['colspan'][$row_key])) {
            $build['colspan'][$row_key] = [];
          }
          $build['colspan'][$row_key][$cell_key] = $cell['colspan'];
        }
      }
    }
    $build['rows'] = self::renderTableArray($build['rows']);
    return $build;
  }

  /**
   * Prepare the download options.
   *
   * @param array $record
   *   The download record array.
   * @param array $build
   *   The build array for the download.
   *
   * @return array
   *   The options array for this download.
   */
  public static function prepareOptions(array $record, array $build) {
    $options = $record['options'];
    $options['format'] = $options['type'];

    if (empty($options['pane_title'])) {
      if (array_key_exists('view_id', $options) && $options['view_id'] == 'data_search') {
        $options['pane_title'] = (string) t('Data search');
      }
      else {
        // Fallback to the first column header of the second worksheet, which is
        // the first non-meta-data worksheet in the build.
        $data_header = current(array_slice($build['header'], 1, 1));
        $options['pane_title'] = $data_header[0];
      }
    }

    // Setup footnotes.
    if (!empty($build['footnotes'])) {
      $options['footnotes'] = array_values($build['footnotes']);
    }

    // Setup cell formats. These override the column formats.
    if (!empty($build['cell_formats'])) {
      $options['cell_formats'] = array_values($build['cell_formats']);
    }

    // Setup cell alignments.
    if (!empty($build['cell_align'])) {
      $options['cell_align'] = array_values($build['cell_align']);
    }

    // Setup colspans.
    if (!empty($build['colspan'])) {
      $options['colspan'] = array_values($build['colspan']);
    }

    // Add meta data defaults.
    if (empty($options['metadata'])) {
      $options['metadata'] = [];
    }

    $options['metadata'] += [
      'creator' => 'HPC',
      'description' => t('Data by HPC as at @date', [
        '@date' => date('Y-n-j'),
      ]),
    ];
    // Move meta data into root level so that phpexcel will pick them up.
    $options += $options['metadata'];

    // Make the header available for all steps in hook_phpexcel_export().
    $options['header'] = $build['header'];

    // Get a temp name.
    $temp_name = self::getTempFileUri($options);
    $options['temp_name'] = $temp_name;

    // Update the options in the database.
    $record['options'] = $options;
    DownloadRecord::updateRecord($record);

    return $options;
  }

  /**
   * Render items in the given table structure.
   *
   * @param array $table
   *   A table array that can still contain render objects/arrays.
   *
   * @return array
   *   The rendered table array.
   */
  public static function renderTableArray(array $table) {
    foreach ($table as $key => $value) {
      if (!is_array($value) && !is_object($value)) {
        continue;
      }
      $table[$key] = self::renderValue($value);
    }
    return $table;
  }

  /**
   * Render a single value.
   *
   * @param mixed $value
   *   The value to render.
   *
   * @return string
   *   The rendered value.
   */
  public static function renderValue($value) {
    // Get renderer service.
    $renderer = &drupal_static(__FUNCTION__ . '_renderer', \Drupal::service('renderer'));

    // This is needed to find if we are running in a drush context.
    // Returns TRUE for UI and FALSE when in drush context.
    $has_render_context = &drupal_static(__FUNCTION__ . '_has_render_context', $renderer->hasRenderContext());

    if (is_object($value) && method_exists($value, 'toString')) {
      return $value->toString();
    }

    if (is_object($value) && $value instanceof MarkupInterface) {
      return (string) $value;
    }

    if (is_array($value) && (!empty($value['#theme']) || !empty($value['#type']) || !empty($value['#plain_text']))) {
      $render_value = $has_render_context ? $renderer->render($value) : $renderer->renderPlain($value);
      return trim(strip_tags(Html::decodeEntities((string) $render_value)));
    }

    if (is_array($value) && array_key_exists('data', $value)) {
      if (is_array($value['data']) && (!empty($value['data']['#theme']) || !empty($value['data']['#type']) || !empty($value['data']['#plain_text']))) {
        $render_value = $has_render_context ? $renderer->render($value['data']) : $renderer->renderPlain($value['data']);
        $value['data'] = trim(strip_tags(Html::decodeEntities((string) $render_value)));
      }
      elseif (is_object($value['data'])) {
        $value['data'] = self::renderValue($value['data']);
      }
      if (array_key_exists('excel_format', $value) && empty($value['excel_format'])) {
        unset($value['excel_format']);
      }
      if (count($value) == 1) {
        $value = $value['data'];
      }
      return $value;
    }
    elseif (is_array($value)) {
      return self::renderTableArray($value);
    }

    return trim(strip_tags(Html::decodeEntities($value)));
  }

  /**
   * Sanitize the given sheet title.
   *
   * @param string $title
   *   The input string.
   *
   * @return string
   *   A sanitized and truncated string.
   */
  public static function sanitizeSheetTitle($title) {
    // Some of the printable ASCII characters are invalid:  * : / \ ? [ ].
    $invalid_characters = ['*', ':', '/', '\\', '?', '[', ']'];
    if (str_replace($invalid_characters, ' ', $title) !== $title) {
      // Remove bad characters from sheet name.
      $title = str_replace($invalid_characters, ' ', $title);
    }
    return strlen($title) > 31 ? Unicode::truncate($title, 31) : $title;
  }

  /**
   * Get a default value based on the excel format string.
   *
   * @param string $excel_format
   *   A string indicating the excel format.
   *
   * @return string|int
   *   Either int 0 or an empty string.
   */
  public static function getDefaultValueByFormat($excel_format) {
    $number_formats = [
      'number',
      'currency',
      'percentage',
    ];
    return in_array($excel_format, $number_formats) ? 0 : '';
  }

  /**
   * Build the file data for the current download.
   *
   * @param array $build
   *   The build array for this download.
   * @param array $meta_data
   *   The meta data array.
   *
   * @return array
   *   The full file data array.
   */
  public static function buildFileData(array $build, array $meta_data) {
    $header = [
      self::sanitizeSheetTitle('Meta data') => [],
      self::sanitizeSheetTitle('Export data') => $build['header'],
    ];
    $sheets = [
      self::sanitizeSheetTitle('Meta data') => $meta_data,
      self::sanitizeSheetTitle('Export data') => $build['rows'],
    ];
    $file_data = ['header' => $header, 'rows' => $sheets];
    return $file_data;
  }

  /**
   * Get a URI for a temporary file to write too.
   */
  public static function getTempFileUri($options) {
    // Create a temporary file, so that downloads that might be going on will
    // not be interfered with during the possibly long running file generation.
    return 'temporary://' . $options['file_name'] . '-' . microtime(TRUE) . '.' . $options['format'];
  }

  /**
   * Generate a data file based on the given options.
   *
   * @param array $download_record
   *   The download record array.
   * @param array $data
   *   The data to be written. This must be in a format that would be consumable
   *   by theme_table().
   * @param array $options
   *   An array with file options.
   *
   * @return bool
   *   Returns TRUE if file generation was successful; otherwise returns FALSE.
   */
  public static function writeFile(array $download_record, array $data, array $options) {
    // Check if we can write to the filesystem.
    if (!is_writable(dirname($options['file_path']))) {
      \Drupal::logger('hpc_downloads')->error('File system is not writable.');
      DownloadRecord::closeRecord($download_record, DownloadRecord::STATUS_ERROR);
      return FALSE;
    }

    // For huge data sets, e.g. in custom search, PHP Excel might get problems
    // processing all the data. To prevent this, we disable timeouts here.
    set_time_limit(0);
    module_load_include('inc', 'phpexcel');

    // This is very slow for larger documents, or for documents with a lot of
    // columns, like in data search.
    ini_set('memory_limit', '512M');
    $status = phpexcel_export($data['header'], $data['rows'], \Drupal::service('file_system')->realpath($options['temp_name']), $options);
    set_time_limit(30);

    return self::finaliseFileGeneration($status == PHPEXCEL_SUCCESS, $options, $download_record);
  }

  /**
   * Finalise the file generation.
   *
   * @param bool $status
   *   The status of the file writing.
   * @param array $options
   *   An array of file options.
   * @param array $download_record
   *   The download record.
   *
   * @return bool
   *   TRUE if the file has been moved to the final location, FALSE otherwhise.
   */
  public static function finaliseFileGeneration($status, array $options, array $download_record) {
    $temp_name = $options['temp_name'];

    if (!$status) {
      // Save the success status.
      $download_record['message'] = 'Failed to save the data file.';
      DownloadRecord::closeRecord($download_record, DownloadRecord::STATUS_ERROR);
      FileHelper::deleteFile($temp_name);
      return FALSE;
    }

    // Move temporary file to the final destination.
    $file_path_final = FileHelper::moveFile($temp_name, $options['file_path']);
    if ($file_path_final === FALSE) {
      if (is_file($temp_name)) {
        \Drupal::logger('hpc_downloads')->error('Failed to delete temporary download file %temp_file.', [
          '%temp_file' => $temp_name,
        ]);
        $message = t('Failed to delete temporary download file %temp_file.', [
          '%temp_file' => $temp_name,
        ]);
      }
      else {
        \Drupal::logger('hpc_downloads')->error('Failed to move temporary download file %temp_file from temporary directory to the downloads directory.', [
          '%temp_file' => $temp_name,
        ]);
        $message = t('Failed to move temporary download file %temp_file from temporary directory to the downloads directory.', [
          '%temp_file' => $temp_name,
        ]);
      }
      $download_record['message'] = $message;
      DownloadRecord::closeRecord($download_record, DownloadRecord::STATUS_ERROR);
      return FALSE;
    }

    // Store the final path.
    $download_record['file_path'] = $file_path_final;
    DownloadRecord::updateRecord($download_record);

    // All done. now close the record with success status.
    DownloadRecord::closeRecord($download_record);
    return TRUE;
  }

}
