<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Helper class for context handling.
 */
class ContextHelper {

  /**
   * Get the current node context if any.
   */
  public static function getNodeFromContexts($contexts) {
    $context_names = [
      'layout_builder.entity',
      'entity',
      'node',
      'node_from_original_id',
    ];

    foreach ($context_names as $context_name) {
      if (empty($contexts[$context_name])) {
        continue;
      }
      try {
        /** @var \Drupal\Core\Plugin\Context\EntityContext $context */
        $context = $contexts[$context_name];
        if (!$context->hasContextValue()) {
          continue;
        }
        $context_definition = $context->getContextDefinition();
        if (!$context_definition instanceof EntityContextDefinition) {
          continue;
        }
        $node_context = $context->getContextValue();
        if ($node_context instanceof NodeInterface) {
          return $node_context;
        }
        elseif (is_scalar($node_context) && $node = Node::load($node_context)) {
          return $node;
        }
      }
      catch (ContextException $e) {
        // Fail silently, we will handle this.
      }
    }
    return NULL;
  }

}
