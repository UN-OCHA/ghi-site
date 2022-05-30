<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'FeaturedOperations' block.
 *
 * @Block(
 *  id = "global_featured_operations",
 *  admin_label = @Translation("Featured operations"),
 *  category = @Translation("Global"),
 *  context_definitions = {
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class FeaturedOperations extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $year = $this->getContextValue('year');

    // Just embed a view.
    return [
      '#type' => 'view',
      '#name' => 'featured_sections',
      '#display_id' => 'block_sections_featured_3',
      '#arguments' => [
        $year,
      ],
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
