<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\ghi_blocks\Map\MapPayload;

/**
 * Interface for block plugins that lazy-load Mapbox map data.
 */
interface LazyMapBlockInterface {

  /**
   * Build the lazy map payload.
   *
   * @param string $map_id
   *   The map container ID from the initial page render.
   *
   * @return \Drupal\ghi_blocks\Map\MapPayload
   *   The lazy map payload.
   */
  public function buildLazyMapPayload(string $map_id): MapPayload;

}
