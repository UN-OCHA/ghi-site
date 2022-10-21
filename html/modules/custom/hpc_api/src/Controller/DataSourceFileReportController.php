<?php

namespace Drupal\hpc_api\Controller;

use Drupal\hpc_api\Helpers\QueryHelper;

/**
 * Controller class for a data source files.
 */
class DataSourceFileReportController extends BaseFileReportController {

  /**
   * {@inheritdoc}
   */
  public function getFiles() {
    return $this->fileSystem->scanDirectory(QueryHelper::IMPORT_DIR, '/.*/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePath($filename) {
    return $this->fileSystem->realpath(rtrim(QueryHelper::IMPORT_DIR, '/') . '/' . $filename);
  }

}
