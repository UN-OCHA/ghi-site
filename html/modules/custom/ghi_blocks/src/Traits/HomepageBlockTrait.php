<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\node\NodeInterface;

/**
 * Trait for homepage blocks.
 */
trait HomepageBlockTrait {

  /**
   * Return the available homepages keyed by year.
   *
   * @return \Drupal\ghi_homepage\Entity\Homepage[]
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
    $nodes = array_combine($years, $nodes);
    krsort($nodes);
    return $nodes;
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
    else {
      $current_year = $this->getPageArgument('year');
    }
    $options = [];
    foreach ($years as $year) {
      $is_active = $year == $current_year;
      $options[$year] = $is_active ? $year : Link::fromTextAndUrl($year, $this->getHomepageUrlForYear($year));
    }
    return [
      '#theme' => 'year_switcher',
      '#years' => $options,
      '#current_year' => $current_year ?? array_key_first($options),
      '#attached' => [
        'library' => ['ghi_blocks/homepage.switcher'],
      ],
    ];
  }

  /**
   * Get the homepage url for the given year.
   *
   * @param int $year
   *   The year.
   *
   * @return \Drupal\Core\Url
   *   The url object.
   */
  protected function getHomepageUrlForYear($year) {
    $page = $this->getHomepageEntity();
    return Url::fromUserInput(str_replace('{year}', $year, $page->getPath()));
  }

  /**
   * Retrieve the page config entity for the homepage.
   *
   * @return \Drupal\page_manager\Entity\Page
   *   The page entity.
   */
  private function getHomepageEntity() {
    $page = &drupal_static(__FUNCTION__, NULL);
    if ($page === NULL) {
      /** @var \Drupal\page_manager\Entity\Page $page */
      $page = $this->entityTypeManager->getStorage('page')->load('homepage');
    }
    return $page;
  }

}
