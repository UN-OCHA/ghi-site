<?php

namespace Drupal\hpc_security\Element;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a trusted callback to alter the render arrays for html tags.
 */
class HpcHtmlPreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Set nonces or hashes.
   */
  public static function preRender($build) {
    if (hpc_security_can_use_nonce()) {
      hpc_security_set_nonce($build);
    }
    else {
      hpc_security_add_hash($build);
    }
    return $build;
  }

}
