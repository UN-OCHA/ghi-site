<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks having optional titles.
 */
interface OverrideDefaultTitleBlockInterface {

  /**
   * Return the form key where the title element should be shown.
   *
   * Only relevant for multistep forms.
   *
   * @return string
   *   The subform key.
   */
  public function getTitleSubform();

}
