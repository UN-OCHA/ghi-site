<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Common logic for map elements.
 */
trait GlobalMapTrait {

  public const STYLE_ID = 'cm3rtb8gi00b801rw6e7tbl4s';

  /**
   * Get the necessary configuration for mapbox.
   *
   * @return array
   *   An options array.
   */
  public static function getMapboxConfig() {
    return [
      'token' => self::getToken(),
      'style_url' => self::getStyleUrl(),
    ];
  }

  /**
   * Check whether to use country outlines.
   *
   * @return bool
   *   TRUE if country outlines should be used, FALSE otherwise.
   */
  public static function useCountryOutlines() {
    $map_config = self::getGlobalMapSettings();
    return !empty($map_config['country_outlines']);
  }

  /**
   * Get the mapbox token.
   *
   * @return string
   *   The mapbox token or the string 'token'.
   */
  private static function getToken() {
    $map_config = self::getGlobalMapSettings();
    $use_proxy = !empty($map_config['mapbox_proxy']);
    return $use_proxy ? 'token' : getenv('MAPBOX_TOKEN');
  }

  /**
   * Get the style url for maps.
   *
   * @return string
   *   A mapbox style url as a string.
   */
  public static function getStyleUrl() {
    return 'mapbox://styles/ocha-hpc/' . self::STYLE_ID . '?optimize=true';
  }

  /**
   * Get the cache tags for the global map config.
   *
   * @return array
   *   An array of relevant cache tags.
   */
  public static function getMapConfigCacheTags() {
    return [
      'config:ghi_blocks.map_settings',
    ];
  }

  /**
   * Get the global map settings.
   *
   * @see \Drupal\ghi_blocks\Form\MapSettingsForm
   */
  public static function getGlobalMapSettings() {
    return \Drupal::config('ghi_blocks.map_settings')->get();
  }

  /**
   * Get the default Map disclaimer.
   *
   * @param string|null $langcode
   *   The language code of the plan.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Return the translated disclaimer.
   */
  public function getDefaultMapDisclaimer(?string $langcode = NULL): string {
    return $this->t(
      'The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.',
      [],
      ['langcode' => $langcode ?? 'en']
    );
  }

}
