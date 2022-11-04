<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\HomepageBlockTrait;

/**
 * Provides a 'Homepages' block.
 *
 * @Block(
 *  id = "global_homepages",
 *  admin_label = @Translation("Homepages (year switchable)"),
 *  category = @Translation("Global"),
 *  default_title = @Translation("Operations"),
 *  context_definitions = {
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"), required = FALSE)
 *  },
 * )
 */
class Homepages extends GHIBlockBase implements OverrideDefaultTitleBlockInterface {

  use HomepageBlockTrait;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $homepages = $this->getHomepages();
    $year = $this->getHomepageYear();
    if (!array_key_exists($year, $homepages)) {
      return [];
    }
    $build = $this->entityTypeManager->getViewBuilder('node')->view($homepages[$year], 'embed');
    return $build;
  }

  /**
   * Get the currently configured year for this homepage block.
   *
   * @return int
   *   The configured year.
   */
  public function getHomepageYear() {
    $available_years = $this->getAvailableYears();
    $requested_year = $this->getPageArgument('year') ?? $this->getBlockConfig()['year'];
    if ($requested_year && in_array($requested_year, $available_years)) {
      return $requested_year;
    }
    return reset($available_years);
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'year' => 2022,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Default year'),
      '#description' => $this->t('Select the year that will show initially when a user first visits the homepage.'),
      '#options' => $this->getHomepageSwitcherOptions(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'year'),
    ];
    return $form;
  }

}
