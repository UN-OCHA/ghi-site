<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Common logic for map elements.
 *
 * Allows to retrieve a static tiles url template to be used in leaflet maps.
 * This takes care of handling the mapbox token depending on whether or not the
 * use of a local proxy is enabled.
 */
trait GlobalMapTrait {

  /**
   * Get the default url template for static tiles.
   *
   * @return string
   *   The URL template to use for leaflet maps.
   */
  public function getStaticTilesUrlTemplate($style_id = NULL) {
    if ($style_id === NULL) {
      $style_id = 'clbfjni1x003m15nu67uwtbly';
    }
    $map_config = $this->getGlobalMapSettings();
    $use_proxy = !empty($map_config['mapbox_proxy']);
    $host = $use_proxy ? '/mapbox' : 'https://api.mapbox.com';
    $token = $use_proxy ? 'token' : getenv('MAPBOX_TOKEN');
    $style_url = 'styles/v1/reliefweb/' . $style_id . '/tiles/256/{z}/{x}/{y}?title=view&access_token=' . $token;
    return $host . '/' . $style_url;
  }

  /**
   * Get the global map settings.
   *
   * @see \Drupal\ghi_blocks\Form\MapSettingsForm
   */
  private function getGlobalMapSettings() {
    return \Drupal::config('ghi_blocks.map_settings')->get();
  }

}
