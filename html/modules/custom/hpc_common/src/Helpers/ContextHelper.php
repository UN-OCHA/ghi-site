<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Plugin\Exception\ContextException;

/**
 * Helper class for context handling.
 */
class ContextHelper {

  /**
   * Get the current node context if any.
   */
  public static function getNodeFromContexts($contexts) {
    $context_names = [
      'node',
      'node_from_original_id',
      'layout_builder.entity',
    ];
    $node_context = NULL;
    foreach ($context_names as $context_name) {
      if (empty($contexts[$context_name])) {
        continue;
      }
      try {
        return $contexts[$context_name]->hasContextValue() ? $contexts[$context_name]->getContextValue() : NULL;
      }
      catch (ContextException $e) {
        // Fail silently, we will handle this.
        $node_context = NULL;
      }
    }
    return $node_context;
  }

}
