<?php

namespace Drupal\ghi_blocks\Plugin\Block\Home;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'IdSheet' block.
 *
 * @Block(
 *  id = "home_id_sheet",
 *  admin_label = @Translation("ID Sheet"),
 *  category = @Translation("Homepage"),
 *  title = false,
 *  context_definitions = {
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"), default_value = "2021")
 *  }
 * )
 */
class IdSheet extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $year = $this->getPageArgument('year');

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'style' => 'width: 100%; height: 400px; background-color: grey; position: relative;',
      ],
      'year' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $year,
        '#attributes' => [
          'style' => 'position: absolute; top: 50%; left: 50%;',
        ],
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
