<?php

namespace Drupal\common_design_subtheme;

use Drupal\Core\Template\Attribute;

/**
 * Class for handling soft limits on tables.
 */
class SoftLimit {

  /**
   * Apply a soft limit to a preprocessed table array.
   *
   * @param array $vars
   *   The variables array describing the table.
   */
  public static function apply(array &$vars) {
    $soft_limit = (int) $vars['soft_limit'];
    $count = 0;
    foreach ($vars['rows'] as &$row) {
      $count++;
      if ($count <= $soft_limit) {
        continue;
      }
      $row['attributes'] = $row['attributes'] ?? new Attribute();
      /** @var \Drupal\Core\Template\Attribute $attributes */
      $attributes = $row['attributes'];
      $attributes->setAttribute('style', 'display: none;');
    }
  }

}
