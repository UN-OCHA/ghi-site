<?php

namespace Drupal\ghi_base_objects\Traits;

/**
 * Helper trait for handling footnote properties.
 */
trait FootnotePropertyTrait {

  /**
   * Get the property options for footnotes.
   *
   * @return string[]
   *   An array of property labels, keyed by their machine name.
   */
  public function getFootnotePropertyOptions() {
    $options = [
      'in_need' => $this->t('In need'),
      'target' => $this->t('Target'),
      'estimated_reach' => $this->t('Estimated reach'),
      'requirements' => $this->t('Requirements'),
    ];
    return array_map(function ($option) {
      return (string) $option;
    }, $options);
  }

}
