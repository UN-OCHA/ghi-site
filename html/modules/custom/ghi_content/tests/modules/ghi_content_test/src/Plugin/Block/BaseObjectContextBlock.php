<?php

namespace Drupal\ghi_content_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block that renders a mapped base object context.
 *
 * @Block(
 *   id = "ghi_content_test_base_object_context",
 *   admin_label = @Translation("Base object context test"),
 *   context_definitions = {
 *     "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"))
 *   }
 * )
 */
class BaseObjectContextBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $plan = $this->getContextValue('plan');
    return [
      '#markup' => 'Mapped plan context: ' . $plan->label(),
    ];
  }

}
