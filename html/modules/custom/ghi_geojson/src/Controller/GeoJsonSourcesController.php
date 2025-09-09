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
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  public $geojson;

  /**
   * GeoJSON directory list service.
   *
   * @var \Drupal\ghi_geojson\GeoJsonDirectoryList
   */
  public $geojsonDirectoryList;

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
    $instance->geojson = $container->get('geojson');
    $instance->geojsonDirectoryList = $container->get('geojson.directory_list');
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
    return $this->geojsonDirectoryList->buildDirectoryListing(GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version);
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
   * Build the rows for the sources tables.
   *
   * @return array
   *   An array of table rows.
   */
  public function buildRows(): array {
    $rows = [];
    $directories = $this->geojson->getFiles(GeoJson::GEOJSON_SOURCE_DIR, '/^[A-Z][A-Z][A-Z]$/');
    foreach ($directories as $directory) {
      $versions = $this->geojson->getFiles($directory->uri);
      foreach (array_reverse($versions) as $version) {
        $url_args = [
          'iso3' => $directory->filename,
          'version' => $version->filename,
        ];
        $inspect_url = Url::fromRoute('ghi_geojson.geojson_sources.directory_listing', $url_args);
        $download_url = Url::fromRoute('ghi_geojson.geojson_sources.download_archive', $url_args);
        $delete_url = Url::fromRoute('ghi_geojson.geojson_sources.delete', $url_args);

        $operation_links = [];
        $operation_links['inspect'] = [
          'title' => $this->t('Inspect'),
          'url' => $inspect_url,
        ];
        $operation_links['download'] = [
          'title' => $this->t('Download archive'),
          'url' => $download_url,
        ];
        if ($delete_url->access() && $version->filename != 'current') {
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
              '#url' => Url::fromRoute('ghi_geojson.geojson_sources.directory_listing', $url_args),
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
