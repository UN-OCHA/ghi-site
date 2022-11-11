<?php

namespace Drupal\ghi_content\Plugin\RemoteSource;

use Drupal\ghi_content\RemoteSource\RemoteSourceBaseHpcContentModule;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides a remote source for the HPC Content Module.
 *
 * @RemoteSource(
 *   id = "hpc_content_module",
 *   label = @Translation("HPC Content Module"),
 *   description = @Translation("Import data directly from the HPC Content Module."),
 * )
 */
class HpcContentModule extends RemoteSourceBaseHpcContentModule implements RemoteSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'base_url' => 'https://content.hpc.tools',
      'basic_auth' => NULL,
      'endpoint' => 'ncms',
      'access_key' => NULL,
    ];
  }

}
