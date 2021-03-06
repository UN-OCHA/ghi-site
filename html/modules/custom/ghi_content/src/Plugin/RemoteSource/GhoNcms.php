<?php

namespace Drupal\ghi_content\Plugin\RemoteSource;

use Drupal\ghi_content\RemoteSource\RemoteSourceBaseGho;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides a remote source for the GHO NCMS.
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
      'basic_auth' => NULL,
      'endpoint' => 'ncms',
      'access_key' => NULL,
    ];
  }

}
