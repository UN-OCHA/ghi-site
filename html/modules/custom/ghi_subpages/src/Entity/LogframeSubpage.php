<?php

namespace Drupal\ghi_subpages\Entity;

use Drupal\layout_builder\LayoutEntityHelperTrait;

/**
 * Entity bundle class for logframe subpage nodes.
 */
class LogframeSubpage extends SubpageNode {

  use LayoutEntityHelperTrait;

  /**
   * Create the page elements for the logframe page.
   */
  public function createPageElements() {
    $logframe_manager = self::logframeManager();
    $section_storage = $logframe_manager->setupLogframePage($this);
    if ($section_storage) {
      $section_storage->save();
    }
  }

  /**
   * Get the logframe manager service.
   *
   * @return \Drupal\ghi_subpages\LogframeManager
   *   The logframe manager service.
   */
  private static function logframeManager() {
    return \Drupal::service('ghi_subpages.logframe_manager');
  }

}
