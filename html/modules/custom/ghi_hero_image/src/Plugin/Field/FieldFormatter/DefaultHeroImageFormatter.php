<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\config_default_image\Plugin\Field\FieldFormatter\ConfigDefaultImageFormatterTrait;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_default_hero_image",
 *   label = @Translation("Hero image or default hero image"),
 *   field_types = {
 *     "ghi_hero_image"
 *   }
 * )
 */
class DefaultHeroImageFormatter extends HeroImageFormatter {

  use ConfigDefaultImageFormatterTrait;

}
