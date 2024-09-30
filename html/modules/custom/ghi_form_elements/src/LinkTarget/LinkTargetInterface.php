<?php

namespace Drupal\ghi_form_elements\LinkTarget;

/**
 * Interface defining link targets.
 */
interface LinkTargetInterface {

  /**
   * Get the admin label to use for the link target.
   *
   * @return string|MarkupInterface
   *   The label to use in the backend.
   */
  public function getAdminLabel();

  /**
   * Get the URL for the link target.
   *
   * @return \Drupal\core\Url
   *   The URL for the link target.
   */
  public function getUrl();

}
