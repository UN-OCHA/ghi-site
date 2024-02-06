<?php

/**
 * @file
 * Preprocess hooks and general purpose theme related functions for GHI Image.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Preprocess an image element.
 */
function ghi_image_preprocess_ghi_image(&$variables) {
  $style = $variables['style'] ?? NULL;
  $responsive_image_style = $variables['responsive_image_style'] ?? NULL;
  $theme = $responsive_image_style ? 'responsive_image' : 'image';

  // For external urls we want to use imagecache_external.
  $uri = \Drupal::service('file_url_generator')->generateString($variables['url']);
  $url = strpos($uri, 'http') === 0 ? Url::fromUri($uri) : Url::fromUserInput($uri);
  if ($url->isExternal()) {
    $theme = $responsive_image_style ? 'imagecache_external_responsive' : ($style ? 'imagecache_external' : 'image');
  }
  $variables['image'] = [
    '#theme' => $theme,
    '#uri' => $variables['url'],
    '#style_name' => $style,
    '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : NULL,
    '#attributes' => array_filter([
      'style' => 'width: 100%',
      'alt' => $variables['alt'],
      'title' => $variables['title'],
    ]),
  ];
  $variables['id'] = Html::getUniqueId('ghi-image');
  $variables['caption_id'] = Html::getUniqueId('ghi-image-caption');
}
