<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\node\NodeInterface;

/**
 * Provides a 'Homepages' block.
 *
 * @Block(
 *  id = "global_homepages",
 *  admin_label = @Translation("Homepages (year switchable)"),
 *  category = @Translation("Global"),
 *  default_title = @Translation("Operations"),
 * )
 */
class Homepages extends GHIBlockBase implements OverrideDefaultTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $homepages = $this->getHomepages();
    $year = $this->getBlockConfig()['year'];
    if (!array_key_exists($year, $homepages)) {
      return [];
    }
    $build = $this->entityTypeManager->getViewBuilder('node')->view($homepages[$year], 'embed');
    return $build;
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
    $homepages = $this->getHomepages();
    $years = array_keys($homepages);
    $form['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Default year'),
      '#description' => $this->t('Select the year that will show initially when a user first visits the homepage.'),
      '#options' => array_combine($years, $years),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'year'),
    ];
    return $form;
  }

  /**
   * Return the available homepages keyed by year.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of nodes.
   */
  private function getHomepages() {
    // Get all published homepages.
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'homepage',
      'status' => NodeInterface::PUBLISHED,
    ]);

    // Key by year.
    $years = array_map(function ($node) {
      return $node->get('field_year')->value;
    }, $nodes);
    return array_combine($years, $nodes);
  }

}
