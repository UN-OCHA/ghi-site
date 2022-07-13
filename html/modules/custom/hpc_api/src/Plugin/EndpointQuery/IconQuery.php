<?php

namespace Drupal\hpc_api\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "icon_query",
 *   label = @Translation("Icon query"),
 *   endpoint = {
 *     "public" = "icon/{icon}",
 *     "version" = "v2"
 *   }
 * )
 */
class IconQuery extends EndpointQueryBase {

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param string $icon
   *   The identifier of the icon.
   *
   * @return string|null
   *   An array of cluster objects, keyed by the cluster id.
   */
  public function getIconEmbedCode($icon) {
    if (empty($icon) || $icon == 'blank_icon') {
      return NULL;
    }
    $svg_data = $this->getData(['icon' => $icon]);
    $svg_content = $svg_data ? $svg_data->svg : NULL;
    return '<div class="cluster-icon">' . $svg_content . '</div>';
  }

}
