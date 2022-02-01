<?php

namespace Drupal\ghi_blocks\Plugin\Block\Country;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'CountryPlanTable' block.
 *
 * @Block(
 *  id = "country_plan_table",
 *  admin_label = @Translation("Plan table"),
 *  category = @Translation("Country elements"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "country" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "country" }),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class CountryPlanTable extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $year = $this->getContextValue('year');
    $country = $this->getContextValue('country');

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'style' => 'width: 100%; height: 400px; background-color: grey; position: relative;',
      ],
      'year' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $country->label() . ': ' . $year,
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
   * Form builder for the config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
