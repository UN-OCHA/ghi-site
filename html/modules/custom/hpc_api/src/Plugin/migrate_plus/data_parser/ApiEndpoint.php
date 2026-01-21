<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_parser;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_plus\Attribute\DataParser;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration.
 *
 * @DataParser(
 *   id = "hpc_api_endpoint",
 *   title = @Translation("HPC Endpoint")
 * )
 */
#[DataParser(
  id: 'hpc_api_endpoint',
  title: new TranslatableMarkup('HPC Endpoint')
)]
class ApiEndpoint extends Json {

}
