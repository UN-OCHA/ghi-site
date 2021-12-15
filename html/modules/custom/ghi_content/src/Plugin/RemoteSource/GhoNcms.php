<?php

namespace Drupal\ghi_content\Plugin\RemoteSource;

use Drupal\ghi_content\RemoteSource\RemoteSourceBaseGho;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides an attachment data item for configuration containers.
 *
 * @RemoteSource(
 *   id = "gho_ncms",
 *   label = @Translation("GHO NCMS"),
 *   description = @Translation("Import data directly from the GHO NCMS website."),
 * )
 */
class GhoNcms extends RemoteSourceBaseGho implements RemoteSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'base_url' => 'https://gho.unocha.org',
      'endpoint' => 'ncms',
      'access_key' => NULL,
    ];
  }

}
