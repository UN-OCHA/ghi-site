<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_sections\Entity\Homepage;
use Drupal\node\NodeInterface;

/**
 * Trait to help manage files uploaded in layout builder blocks.
 */
trait HomepageBlockTrait {

  /**
   * Return the available homepages keyed by year.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of nodes.
   */
  protected function getHomepages() {
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

  /**
   * Return the available homepages keyed by year.
   *
   * @return int[]
   *   An array with keys and values being the available years.
   */
  protected function getAvailableYears() {
    $homepages = $this->getHomepages();
    $years = array_keys($homepages);
    arsort($years);
    return $years;
  }

  /**
   * Return the available homepages keyed by year.
   *
   * @return int[]
   *   An array with keys and values being the available years.
   */
  protected function getHomepageSwitcherOptions() {
    $years = $this->getAvailableYears();
    return array_combine($years, $years);
  }

  /**
   * See if the current page needs a homepage switcher.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  protected function needsHomepageSwitcher() {
    $page = $this->getPage();
    return $page == 'homepage';
  }

  /**
   * Build a homepage year switcher element.
   *
   * @return array
   *   The render array for the year switcher.
   */
  protected function buildHomepageYearSwitcher() {
    if (!$this->needsHomepageSwitcher()) {
      return NULL;
    }
    $years = $this->getAvailableYears();
    $node = $this->getPageNode();
    $current_year = NULL;
    if ($node && $node instanceof Homepage) {
      $current_year = $node->getYear();
    }
    $options = [];
    foreach ($years as $year) {
      $is_active = $year == $current_year;
      $options[$year] = $is_active ? $year : Link::fromTextAndUrl($year, Url::fromUserInput('/home/' . $year));
    }
    return [
      '#theme' => 'year_switcher',
      '#years' => $options,
      '#current_year' => $current_year ?? array_key_first($options),
    ];
  }

}
