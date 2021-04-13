<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration.
 *
 * @DataParser(
 *   id = "hpc_api_endpoint",
 *   title = @Translation("HPC Endpoint")
 * )
 */
class ApiEndpoint extends Json {

}
