<?php

namespace Drupal\ghi_geojson\Controller;

use Drupal\ghi_geojson\GeoJson;
use Drupal\hpc_api\Controller\BaseFileReportController;

/**
 * Controller class for geojson file reports.
 */
class GeoJsonFileReportController extends BaseFileReportController {

  /**
   * {@inheritdoc}
   */
  public function getFiles() {
    return $this->fileSystem->scanDirectory(GeoJson::GEOJSON_DIR, '/.*/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePath($filename) {
    return $this->fileSystem->realpath(rtrim(GeoJson::GEOJSON_DIR, '/') . '/' . $filename);
  }

}
