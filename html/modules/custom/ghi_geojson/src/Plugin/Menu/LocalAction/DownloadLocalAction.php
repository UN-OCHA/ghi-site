<?php

namespace Drupal\ghi_geojson\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines a local action plugin with a dynamic title.
 */
class DownloadLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = (array) $this->pluginDefinition['options'];
    $options['attributes'] = [
      'class' => [
        'download-local-action',
      ],
    ];
    return $options;
  }

}