<?php

namespace Drupal\ghi_geojson;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class GeoJsonDirectoryList {

  use StringTranslationTrait;

  /**
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  public $geojson;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  public $fileSystem;

  /**
   * File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  public $fileUrlGenerator;

  /**
   * Construct the GEOJson service.
   *
   * @param \Drupal\ghi_geojson\GeoJson $geojson
   *   The geojson service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator service.
   */
  public function __construct(GeoJson $geojson, FileSystemInterface $file_system, FileUrlGeneratorInterface $file_url_generator) {
    $this->geojson = $geojson;
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Build a directory listing.
   *
   * @param string $directory
   *   The directory for which to build the listing.
   * @param bool $link
   *   Whether to include links or not.
   *
   * @return array
   *   A render array.
   */
  public function buildDirectoryListing(string $directory, ?bool $link = TRUE): array {
    $items = [];
    $scan_options = [
      'nomask' => '/.min.geojson$/',
    ];
    $entries = $this->geojson->getFiles($directory, NULL, $scan_options);

    foreach ($entries as $entry) {
      $files = is_dir($entry->uri) ? $this->geojson->getFiles($entry->uri, NULL, $scan_options) : NULL;

      if (is_file($entry->uri)) {
        $item = $this->buildFileItems($entry, $link);
      }

      if (is_dir($entry->uri)) {
        $item = [
          '#markup' => $entry->filename . ' (' . count($files) . ' files)',
          '#wrapper_attributes' => [
            'class' => ['directory'],
          ],
          'children' => [],
        ];
        foreach ($files as $file) {
          $item['children'][] = $this->buildFileItems($file, $link);
        }
      }
      $items[] = $item;
    }
    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => ['geojson-directory-listing'],
      ],
      '#attached' => [
        'library' => ['ghi_geojson/geojson_admin'],
      ],
    ];
    return $build;
  }

  /**
   * Build all items belonging to a file.
   *
   * This adds additional items for the minified version of the same file.
   *
   * @param object $file
   *   Object as returned from FileSystem::scanDirectory().
   * @param bool $link
   *   Whether to include links or not.
   *
   * @return array
   *   A render array for a link.
   */
  private function buildFileItems(object $file, ?bool $link = TRUE): array {
    $links = [];
    $links[] = $link ? $this->buildFileLink($file) : $this->buildFileItem($file);
    $minified_file = str_replace('.geojson', '.min.geojson', $file->uri);
    if (file_exists($minified_file)) {
      $minified_file = (object) [
        'uri' => $minified_file,
        'filename' => $this->fileSystem->basename($minified_file),
      ];
      $links[] = ['#markup' => '&nbsp;/&nbsp;'];
      $links[] = $link ? $this->buildFileLink($minified_file) : $this->buildFileItem($minified_file);
    }
    return $links;
  }

  /**
   * Build a file item.
   *
   * @param object $file
   *   Object as returned from FileSystem::scanDirectory().
   * @param string|null $title
   *   An optional title.
   *
   * @return array
   *   A render array for a link.
   */
  private function buildFileItem(object $file, ?string $title = NULL): array {
    return [
      '#markup' => $title ?? $file->filename,
    ];
  }

  /**
   * Build a file item as a link.
   *
   * @param object $file
   *   Object as returned from FileSystem::scanDirectory().
   * @param string|null $title
   *   An optional title.
   *
   * @return array
   *   A render array for a link.
   */
  private function buildFileLink(object $file, ?string $title = NULL): array {
    return [
      '#type' => 'link',
      '#title' => $title ?? $file->filename,
      '#url' => $this->fileUrlGenerator->generate($file->uri),
    ];
  }

}
