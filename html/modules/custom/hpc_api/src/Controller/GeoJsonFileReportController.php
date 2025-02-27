<?php

namespace Drupal\hpc_api\Controller;

use Drupal\hpc_api\GeoJsonService;

/**
 * Controller class for a geojson files.
 */
class GeoJsonFileReportController extends BaseFileReportController {

  /**
   * {@inheritdoc}
   */
  public function getFiles() {
    return $this->fileSystem->scanDirectory(GeoJsonService::GEO_JSON_DIR, '/.*/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePath($filename) {
    return $this->fileSystem->realpath(rtrim(GeoJsonService::GEO_JSON_DIR, '/') . '/' . $filename);
  }

}
