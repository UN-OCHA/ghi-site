<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\ghi_blocks\Map\MapDataFragment;

/**
 * Interface for lazy map blocks that can load smaller JSON fragments.
 *
 * The initial lazy map payload is still responsible for bootstrapping the map
 * and describing the available tabs/variants. Fragment methods are the second
 * stage: they hydrate the selected tab/variant data slice, or one location's
 * modal/sidebar payload, after the map already exists in the browser.
 */
interface LazyMapDataFragmentBlockInterface {

  /**
   * Builds a lazy map data fragment for a tab or variant.
   *
   * A data slice contains the map data needed to render and search one
   * tab/variant, such as locations, metric totals, radius values, and the
   * slice-loaded marker. It deliberately excludes per-location sidebar HTML so
   * large maps do not bring every modal into memory up front.
   *
   * @param string $map_id
   *   The map element id.
   * @param string $data_index
   *   The map data index.
   * @param string|null $variant_id
   *   The selected variant id, if any.
   *
   * @return \Drupal\ghi_blocks\Map\MapDataFragment|null
   *   The map data fragment, or NULL if not found.
   */
  public function buildLazyMapDataFragment(string $map_id, string $data_index, ?string $variant_id = NULL): ?MapDataFragment;

  /**
   * Builds a lazy map modal content fragment for one location.
   *
   * Modal fragments are intentionally narrower than data slices: they return
   * the sidebar/modal content for a single location within the active
   * tab/variant, and are loaded only when that location needs to be shown.
   *
   * @param string $map_id
   *   The map element id.
   * @param string $data_index
   *   The map data index.
   * @param string $object_id
   *   The selected map object id.
   * @param string|null $variant_id
   *   The selected variant id, if any.
   *
   * @return \Drupal\ghi_blocks\Map\MapDataFragment|null
   *   The map modal fragment, or NULL if not found.
   */
  public function buildLazyMapModalFragment(string $map_id, string $data_index, string $object_id, ?string $variant_id = NULL): ?MapDataFragment;

}
