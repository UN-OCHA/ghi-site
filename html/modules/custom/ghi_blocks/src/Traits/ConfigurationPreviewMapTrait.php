<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Url;
use Drupal\ghi_blocks\Map\MapModalContent;

/**
 * Helpers for lazy-loading modal contents in map configuration previews.
 */
trait ConfigurationPreviewMapTrait {

  /**
   * Move map modal content behind a preview-only lazy endpoint.
   *
   * The map still needs access to modal content when a location is selected,
   * but the initial preview response only needs the location geometry and
   * summary values. Storing modal content behind a short-lived token keeps the
   * preview settings payload small without changing each map's modal markup.
   *
   * @param array $map
   *   The map settings array.
   *
   * @return array
   *   The preview-safe map settings.
   */
  protected function getConfigurationPreviewMap(array $map): array {
    $modal_data = MapModalContent::extractFromMap($map);
    $modal_data_token = $this->storeConfigurationPreviewModalData($modal_data);
    if ($modal_data_token) {
      $map['modal_data_url'] = Url::fromRoute('ghi_blocks.map_preview_modal_data', [
        'token' => $modal_data_token,
      ])->toString();
    }
    return $map;
  }

  /**
   * Store extracted map modal content for preview lazy-loading.
   *
   * @param array $modal_data
   *   The extracted modal content entries.
   *
   * @return string|null
   *   A token for lazy-loading preview modal contents, or NULL if there are no
   *   modal contents to store.
   */
  private function storeConfigurationPreviewModalData(array $modal_data): ?string {
    $modal_data = array_filter($modal_data, fn (array $entry) => !empty($entry['modal_contents']) && is_array($entry['modal_contents']));
    if (empty($modal_data)) {
      return NULL;
    }

    $store = $this->keyValueExpirableFactory->get(MapModalContent::CONFIGURATION_PREVIEW_COLLECTION);
    $token = $this->uuid->generate();
    $uid = (int) $this->currentUser->id();

    foreach ($modal_data as $entry) {
      $store->setWithExpire(MapModalContent::buildStoreKey($token, $entry['data_index'], $entry['variant_id']), [
        'uid' => $uid,
        'modal_contents' => $entry['modal_contents'],
      ], MapModalContent::CONFIGURATION_PREVIEW_TTL);
    }

    return $token;
  }

}
