<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\node\Entity\Node;

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
      'layout_builder.entity',
      'node_from_original_id',
    ];
    $node_context = NULL;
    foreach ($context_names as $context_name) {
      if (empty($contexts[$context_name])) {
        continue;
      }
      try {
        $node_context = $contexts[$context_name]->hasContextValue() ? $contexts[$context_name]->getContextValue() : NULL;
        $node = is_object($node_context) ? $node_context : ($node_context ? Node::load($node_context) : NULL);
        if ($node) {
          return $node;
        }
      }
      catch (ContextException $e) {
        // Fail silently, we will handle this.
        $node_context = NULL;
      }
    }
    return is_object($node_context) ? $node_context : ($node_context ? Node::load($node_context) : NULL);
  }

}
