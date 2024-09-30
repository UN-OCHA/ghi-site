<?php

namespace Drupal\ghi_form_elements\LinkTarget;

use Drupal\Core\Url;

/**
 * A link target class representing external links.
 */
class ExternalLinkTarget implements LinkTargetInterface {

  /**
   * The admin label.
   *
   * @var string
   */
  private $adminLabel;

  /**
   * The target node.
   *
   * @var \Drupal\Core\Url
   */
  private $url;

  /**
   * Construct a new external link target.
   *
   * @param string $admin_label
   *   An admin label for internal use in the backend.
   * @param \Drupal\core\Url $url
   *   The target url.
   */
  public function __construct($admin_label, Url $url) {
    $this->adminLabel = $admin_label;
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLabel() {
    return $this->adminLabel;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

}
