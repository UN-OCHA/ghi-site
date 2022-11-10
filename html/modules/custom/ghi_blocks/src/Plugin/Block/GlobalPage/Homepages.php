<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\HomepageBlockTrait;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadContainerInterface;

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
class Homepages extends GHIBlockBase implements OverrideDefaultTitleBlockInterface, HPCDownloadContainerInterface {

  use HomepageBlockTrait;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $homepage = $this->getHomepage();
    if (!$homepage) {
      return [];
    }
    $build = $this->entityTypeManager->getViewBuilder('node')->view($homepage, 'embed');
    return $build;
  }

  /**
   * Get the current homepage container.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The homepage container node.
   */
  private function getHomepage() {
    $homepages = $this->getHomepages();
    $year = $this->getHomepageYear();
    return $homepages[$year] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function findContainedPlugin($plugin_id, $block_uuid) {
    $homepages = $this->getHomepages();
    foreach ($homepages as $homepage) {
      if ($block = BlockHelper::getBlockInstanceFromEntity($homepage, $plugin_id, $block_uuid)) {
        return $block;
      }
    }
    return NULL;
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
