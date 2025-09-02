<?php

namespace Drupal\ghi_geojson;

use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GeoJson {

  const GEOJSON_SOURCE_DIR = 'public://geojson_sources';
  const GEOJSON_DIR = 'public://geojson';
  const ARCHIVE_TEMP_DIR = 'temporary://geojson';

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  public $fileSystem;

  /**
   * Construct the GEOJson service.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * Get the path to the geojson file inside the public file directory.
   *
   * This checks if a geojson file for this location is present in the source,
   * and if so, it makes sure to copy it over to the public file system.
   *
   * @param \Drupal\ghi_geojson\GeoJsonLocationInterface $location
   *   The location for which to retrieve the geojson filepath.
   *
   * @return string
   *   The path to the geojson file inside the public file directory.
   */
  public function getGeoJsonPublicFilePath(GeoJsonLocationInterface $location) {
    $public_filepath = GeoJson::GEOJSON_DIR . '/' . $location->getUuid() . '.geojson';
    if (!file_exists($public_filepath) && $filepath = $this->getGeoJsonSourceFilePath($location)) {
      copy($filepath, $public_filepath);
    }
    return file_exists($public_filepath) ? $public_filepath : NULL;
  }

  /**
   * Get the path to the geojson shape file for the location.
   *
   * @param \Drupal\ghi_geojson\GeoJsonLocationInterface $location
   *   The location for which to retrieve the geojson filepath.
   * @param string $version
   *   The version to retrieve.
   * @param bool $minified
   *   Whether a minified file should be retrieved.
   *
   * @return string|null
   *   The path to the locally stored file inside our module directory. Or NULL
   *   if the file can't be found.
   */
  public function getGeoJsonSourceFilePath(GeoJsonLocationInterface $location, $version = NULL, $minified = TRUE) {
    $iso3 = $location->getIso3();
    if (empty($iso3)) {
      return NULL;
    }
    $version = $version ?? $location->getGeoJsonVersion();
    $source_directory = self::GEOJSON_SOURCE_DIR . '/' . $iso3;
    if ($version != 'current') {
      $directories = $this->getFiles($source_directory, '^/[0-9][0-9][0-9][0-9]/$');
      $directory_years = array_map(function ($directory) {
        return $directory->filename;
      }, $directories);
      $versions = array_filter($directory_years, function ($directory_year) use ($version) {
        return (int) $directory_year >= (int) $version;
      });
      rsort($versions, SORT_NUMERIC);
      $version = reset($versions) ?: 'current';
    }

    // The source file for countries comes from a local asset.
    $filepath = $this->buildGeoJsonSourceFilePath($location, $version, $minified);
    if (!$filepath) {
      return NULL;
    }

    $filepath_asset = $source_directory . '/' . $filepath;
    if (!file_exists($filepath_asset)) {
      // If the file is not found, try the non-minified version once.
      return $minified ? $this->getGeoJsonSourceFilePath($location, $version, FALSE) : NULL;
    }
    return $filepath_asset;
  }

  /**
   * Build the path to the geojson source files inside this modules directory.
   *
   * @param \Drupal\ghi_geojson\GeoJsonLocationInterface $location
   *   The location for which to retrieve the geojson filepath.
   * @param string $version
   *   The version to retrieve.
   * @param bool $minified
   *   Whether a minified file should be retrieved.
   *
   * @return string
   *   A path relative to the this modules geojson asset file directory in
   *   public://geojson_sources.
   */
  private function buildGeoJsonSourceFilePath(GeoJsonLocationInterface $location, $version = NULL, $minified = TRUE) {
    $iso3 = $location->getIso3();
    if (empty($iso3)) {
      return NULL;
    }
    $version = $version ?? $location->getGeoJsonVersion();
    $path_parts = [
      $version,
    ];
    $admin_level = $location->getAdminLevel();
    $pcode = $location->getPcode();
    if ($admin_level == 0) {
      // Country shape files are directly in the root level.
      $path_parts[] = $iso3 . '_0' . ($minified ? '.min' : '') . '.geojson';
    }
    elseif (!empty($admin_level) && !empty($pcode)) {
      // Admin 1+ shape files are inside a level specific subdirectory.
      $path_parts[] = 'adm' . $admin_level;
      // And they are simply named like their pcode.
      $path_parts[] = $pcode . ($minified ? '.min' : '') . '.geojson';
    }
    else {
      return NULL;
    }
    return implode('/', $path_parts);
  }

  /**
   * Get the files inside the given directory.
   *
   * @param string $directory
   *   The directory.
   * @param array $pattern
   *   An optional preg_match() regular expression to match the files against.
   * @param array $options
   *   An optional array with options for FileSystem::scanDirectory().
   *
   * @return array
   *   An array of files in the directory.
   */
  public function getFiles(string $directory, $pattern = NULL, array $options = []): array {
    $files = $this->fileSystem->scanDirectory($directory, $pattern ?? '/.*/', $options + [
      'recurse' => FALSE,
    ]);
    ksort($files);
    return $files;
  }

  /**
   * Get the number of files inside the given directory.
   *
   * @param string $directory
   *   The directory.
   * @param array|null $exclude
   *   An optional array of string filters.
   *
   * @return int
   *   The number of files in the directory, optionally filtered by the given
   *   exclude string.
   */
  public function getFileCount($directory, ?array $exclude = NULL): int {
    if (!is_dir($directory)) {
      return 0;
    }
    $files = $this->getFiles($directory);
    if (!empty($exclude)) {
      $files = array_filter($files, function ($file) use ($exclude) {
        foreach ($exclude as $exclude_string) {
          if (str_contains($file->filename, $exclude_string)) {
            return FALSE;
          }
        }
        return TRUE;
      });
    }
    return count($files);
  }

  /**
   * Create an archive file and place it in temporary storage.
   *
   * @param string $iso3
   *   @todo
   * @param string $version
   *   @todo
   *
   * @return string|mixed
   *   @todo
   */
  public function createArchiveFile($iso3, $version): mixed {
    $file_system = $this->fileSystem;

    // Prepare the directory.
    $archive_temp_dir = self::ARCHIVE_TEMP_DIR;
    $directory_status = $file_system->prepareDirectory($archive_temp_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$directory_status) {
      return FALSE;
    }

    // Create the archive file.
    $zip = new \ZipArchive();
    $zip_path = $file_system->realpath($archive_temp_dir . '/' . $iso3 . '-' . $version . '.zip');
    if ($zip->open($zip_path, \ZipArchive::CREATE) !== TRUE) {
      return FALSE;
    }

    // Add each file to the zip container.
    foreach ($this->getFiles(self::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version) as $file) {
      if (is_file($file->uri)) {
        $zip->addFile($file_system->realpath($file->uri), $file->filename);
      }
      else if (is_dir($file->uri)) {
        $zip->addEmptyDir($file->filename);
        foreach ($this->getFiles($file->uri) as $_file) {
          if (!is_file($_file->uri)) {
            continue;
          }
          $zip->addFile($file_system->realpath($_file->uri), $file->filename . '/' . $_file->filename);
        }
      }
    }

    // Close and save.
    $zip->close();

    // Check that the file now really exists.
    if (!file_exists($zip_path)) {
      return FALSE;
    }
    return $zip_path;
  }

}