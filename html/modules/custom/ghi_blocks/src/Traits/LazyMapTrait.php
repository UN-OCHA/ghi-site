<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Url;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Helpers for map data loaded from saved or unsaved block state.
 */
trait LazyMapTrait {

  /**
   * Get the page URI used to resolve the current map configuration.
   *
   * @return string
   *   The editor route for unsaved layouts, otherwise the current page URI.
   */
  protected function getMapPageUri(): string {
    $section_storage = $this->routeMatch->getParameter('section_storage');
    if ($section_storage instanceof SectionStorageInterface) {
      // IPE can disable the core Layout tab, so preserve the actual editor
      // route. getCurrentUri() may instead point to the saved public page.
      return $this->requestStack->getCurrentRequest()->getPathInfo();
    }
    return $this->getCurrentUri();
  }

  /**
   * Build the normal lazy map data URL for a saved block.
   *
   * @param string $map_id
   *   The map id.
   * @param array $query
   *   Additional query parameters.
   *
   * @return string|null
   *   The lazy data URL, or NULL for preview and unsaved blocks.
   */
  protected function getMapDataUrl(string $map_id, array $query = []): ?string {
    if ($this->isPreview() || !$this->getUuid()) {
      return NULL;
    }
    return Url::fromRoute('ghi_blocks.map_data', [
      'plugin_id' => $this->getPluginId(),
      'block_uuid' => $this->getUuid(),
    ], [
      'query' => array_filter([
        'current_uri' => $this->getMapPageUri(),
        'map_id' => $map_id,
      ] + $query, fn ($value) => $value !== NULL && $value !== ''),
    ])->toString();
  }

  /**
   * Apply the shared preview token to a map and its derived resources.
   *
   * @param array $map
   *   The map settings array.
   * @param string $map_id
   *   The map id.
   * @param bool $include_fragment_url
   *   Whether the map loads additional data fragments.
   *
   * @return array
   *   The preview-safe map settings.
   */
  protected function preparePreviewMap(array $map, string $map_id, bool $include_fragment_url = FALSE): array {
    $modal_data = MapModalContent::extractFromMap($map);
    if (!$this->isConfigurationPreview()) {
      return $map;
    }

    $token = $this->getPreviewStateToken();
    foreach ($modal_data as $entry) {
      if (empty($entry['modal_contents']) || !is_array($entry['modal_contents'])) {
        continue;
      }
      $resource_key = MapModalContent::buildResourceKey($map_id, $entry['data_index'], $entry['variant_id']);
      $this->previewStateManager->storeResource($token, MapModalContent::RESOURCE_NAMESPACE, $resource_key, $entry['modal_contents']);
    }

    $map['modal_data_url'] = Url::fromRoute('ghi_blocks.block_preview_map_modal_data', [
      'preview_state_token' => $token,
    ], [
      'query' => ['map_id' => $map_id],
    ])->toString();
    if ($include_fragment_url) {
      $map['slice_data_url'] = Url::fromRoute('ghi_blocks.block_preview_map_data_fragment', [
        'preview_state_token' => $token,
      ], [
        'query' => ['map_id' => $map_id],
      ])->toString();
    }
    return $map;
  }

}
