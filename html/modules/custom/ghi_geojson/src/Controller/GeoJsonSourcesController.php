<?php

namespace Drupal\ghi_geojson\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ghi_geojson\GeoJson;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller class for geojson file reports.
 */
class GeoJsonSourcesController extends ControllerBase {

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
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  public $geojson;

  /**
   * The layout builder modal config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $modalConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GeoJsonSourcesController {
    /** @var \ Drupal\ghi_geojson\Controller\GeoJsonSourcesController $instance */
    $instance = new static();
    $instance->fileSystem = $container->get('file_system');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->geojson = $container->get('geojson');
    $instance->modalConfig = $container->get('config.factory')->get('layout_builder_modal.settings');
    return $instance;
  }

  /**
   * Controller callback for the sources page.
   *
   * @return array
   *    A render array with the page content.
   */
  public function sourcesPage(): array {
    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Country code'),
        $this->t('Version'),
        $this->t('adm1'),
        $this->t('adm2'),
        $this->t('adm3'),
        $this->t('Operations'),
      ],
      '#rows' => $this->buildRows(),
    ];
    return $table;
  }

  /**
   * Title callback for the directory listing.
   *
   * @param string $iso3
   *   The ISO3 code for the country to be viewed.
   * @param string $version
   *   The version to be viewed.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The title.
   */
  public function directoryTitle(string $iso3, string $version): MarkupInterface {
    return $this->t('File list for @iso3 (@version)', [
      '@iso3' => $iso3,
      '@version' => $version,
    ]);
  }

  /**
   * Controller callback for the directory listing.
   *
   * @param string $iso3
   *   The ISO3 code for the country to be viewed.
   * @param string $version
   *   The version to be viewed.
   *
   * @return array
   *   A render array with the page content.
   */
  public function directoryListing(string $iso3, string $version): array {
    $items = [];
    $scan_options = [
      'nomask' => '/.min.geojson$/',
    ];
    $entries = $this->geojson->getFiles(GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version, NULL, $scan_options);

    foreach ($entries as $entry) {
      $files = is_dir($entry->uri) ? $this->geojson->getFiles($entry->uri, NULL, $scan_options) : NULL;

      if (is_file($entry->uri)) {
        $item = $this->buildFileLinks($entry);
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
          $item['children'][] = $this->buildFileLinks($file);
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
   * Controller callback for the directory listing.
   *
   * @param string $iso3
   *   The ISO3 code for the country to be viewed.
   * @param string $version
   *   The version to be viewed.
   *
   * @return array
   *   A render array with the page content.
   */
  public function directoryDownload(string $iso3, string $version) {
    $archive_file = $this->geojson->createArchiveFile($iso3, $version);
    if (!$archive_file) {
      return new Response('There was an error', 400);
    }
    $headers = [
      'Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment;filename="' . $this->fileSystem->basename($archive_file) . '"',
    ];
    return new BinaryFileResponse($archive_file, 200, $headers, TRUE);
  }

  /**
   * Delete a country version directory.
   *
   * @param string $iso3
   *   The ISO3 code for the country to be deleted.
   * @param string $version
   *   The version to be deleted.
   *
   * @return bool
   *   The result of the delete operation.
   */
  public function deleteVersion(string $iso3, string $version): bool {
    if ($version == 'current') {
      throw new \Exception(sprintf('Current GeoJSON versions cannot be deleted (country: %s)', $iso3));
    }
    return $this->fileSystem->deleteRecursive($this->geojson->getSourceDirectoryPath($iso3, $version));
  }

  /**
   * Build all links to a file.
   *
   * This adds additional links for the minified version of the same file.
   *
   * @param object $file
   *   Object as returned from FileSystem::scanDirectory().
   *
   * @return array
   *   A render array for a link.
   */
  public function buildFileLinks(object $file): array {
    $links = [];
    $links[] = $this->buildFileLink($file);
    $minified_file = str_replace('.geojson', '.min.geojson', $file->uri);
    if (file_exists($minified_file)) {
      $links[] = ['#markup' => '&nbsp;/&nbsp;'];
      $links[] = $this->buildFileLink((object) [
        'uri' => $minified_file,
        'filename' => $this->fileSystem->basename($minified_file),
      ]);
    }
    return $links;
  }

  /**
   * Build a link to a file.
   *
   * @param object $file
   *   Object as returned from FileSystem::scanDirectory().
   * @param string|null $title
   *   An optional title.
   *
   * @return array
   *   A render array for a link.
   */
  public function buildFileLink(object $file, ?string $title = NULL): array {
    return [
      '#type' => 'link',
      '#title' => $title ?? $file->filename,
      '#url' => $this->fileUrlGenerator->generate($file->uri),
    ];
  }

  /**
   * Build the rows for the sources tables.
   *
   * @return array
   *   An array of table rows.
   */
  public function buildRows(): array {
    $rows = [];
    $directories = $this->geojson->getFiles(GeoJson::GEOJSON_SOURCE_DIR);
    foreach ($directories as $directory) {
      $versions = $this->geojson->getFiles($directory->uri);
      foreach (array_reverse($versions) as $version) {
        $operation_links = [];
        $operation_links['inspect'] = [
          'title' => $this->t('Inspect'),
          'url' => Url::fromRoute('ghi_geojson.geojson_sources.directory_listing', [
            'iso3' => $directory->filename,
            'version' => $version->filename,
          ]),
        ];
        $operation_links['download'] = [
          'title' => $this->t('Download archive'),
          'url' => Url::fromRoute('ghi_geojson.geojson_sources.download_archive', [
            'iso3' => $directory->filename,
            'version' => $version->filename,
          ]),
        ];
        if ($version->filename != 'current') {
          $operation_links['delete'] = [
            'title' => $this->t('Delete version'),
            'url' => Url::fromRoute('ghi_geojson.geojson_sources.delete', [
              'iso3' => $directory->filename,
              'version' => $version->filename,
            ]),
            'attributes' => [
              'class' => [
                'use-ajax',
              ],
              'data-dialog-type' => 'dialog',
              'data-dialog-options' => Json::encode([
                'width' => $this->modalConfig->get('modal_width'),
                'height' => $this->modalConfig->get('modal_height'),
                'target' => 'layout-builder-modal',
                'autoResize' => $this->modalConfig->get('modal_autoresize'),
                'modal' => TRUE,
              ]),
            ],
          ];
        }

        $row = [
          $directory->filename,
          [
            'data' => [
              '#type' => 'link',
              '#title' => $version->filename,
              '#url' => Url::fromRoute('ghi_geojson.geojson_sources.directory_listing', [
                'iso3' => $directory->filename,
                'version' => $version->filename,
              ]),
            ],
          ],
          $this->geojson->getFileCount($version->uri . '/adm1', ['.min.geojson']),
          $this->geojson->getFileCount($version->uri . '/adm2', ['.min.geojson']),
          $this->geojson->getFileCount($version->uri . '/adm3', ['.min.geojson']),
          [
            'data' => [
              '#type' => 'dropbutton',
              '#links' => $operation_links,
            ],
          ],
        ];
        $rows[] = $row;
      }
    }

    return $rows;
  }

}
