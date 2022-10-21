<?php

namespace Drupal\hpc_api\Controller;

use Drupal\hpc_api\Plugin\EndpointQuery\IconQuery;

/**
 * Controller class for a icon files.
 */
class IconFileReportController extends BaseFileReportController {

  /**
   * {@inheritdoc}
   */
  public function getFiles() {
    return $this->fileSystem->scanDirectory(IconQuery::IMPORT_DIR, '/.*/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePath($filename) {
    return $this->fileSystem->realpath(rtrim(IconQuery::IMPORT_DIR, '/') . '/' . $filename);
  }

}
