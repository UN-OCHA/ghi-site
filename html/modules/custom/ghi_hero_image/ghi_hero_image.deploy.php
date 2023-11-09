<?php

/**
 * @file
 * Post update functions for GHI Hero Image.
 */

use Drupal\ghi_hero_image\HeroImageWidgetCropManager;

/**
 * Flush style derivatives.
 */
function ghi_hero_image_deploy_flush_styles(&$sandbox) {
  /** @var \Drupal\ghi_hero_image\HeroImageWidgetCropManager $hero_image_widget_crop_manager */
  $hero_image_widget_crop_manager = \Drupal::service('hero_image_widget_crop.manager');
  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = \Drupal::service('file_system');
  $crop_type_names = HeroImageWidgetCropManager::CROP_TYPES;
  foreach ($crop_type_names as $crop_type_name) {
    /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
    $image_styles = $hero_image_widget_crop_manager->getImageStylesByCrop($crop_type_name);
    foreach ($image_styles as $image_style) {
      // Flush the image styles.
      $image_style->flush();
      // And recursively delete all files for this style to also catch the webp
      // derivatives.
      $file_system->deleteRecursive("public://styles/{$image_style->id()}");
    }
  }
}
