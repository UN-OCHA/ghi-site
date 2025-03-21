<?php

namespace Drupal\ghi_base_objects\Controller;

use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\hpc_api\Controller\BaseFileReportController;

/**
 * Controller class for geojson file reports.
 */
class GeoJsonFileReportController extends BaseFileReportController {

  /**
   * {@inheritdoc}
   */
  public function getFiles() {
    return $this->fileSystem->scanDirectory(Location::GEO_JSON_DIR, '/.*/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePath($filename) {
    return $this->fileSystem->realpath(rtrim(Location::GEO_JSON_DIR, '/') . '/' . $filename);
  }

}
