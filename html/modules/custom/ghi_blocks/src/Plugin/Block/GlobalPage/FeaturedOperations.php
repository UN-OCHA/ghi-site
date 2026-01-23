<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'FeaturedOperations' block.
 */
#[Block(
  id: 'global_featured_operations',
  admin_label: new TranslatableMarkup('Featured operations'),
  category: new TranslatableMarkup('Global'),
  context_definitions: [
    'year' => new ContextDefinition(data_type: 'integer', label: new TranslatableMarkup("Year")),
  ]
)]
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
