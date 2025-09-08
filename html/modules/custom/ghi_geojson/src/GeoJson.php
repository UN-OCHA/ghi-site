<?php

namespace Drupal\ghi_geojson;

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class GeoJson {

  use StringTranslationTrait;

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
   * File system service.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  public $symfonyFileSystem;

  /**
   * Construct the GEOJson service.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
    $this->symfonyFileSystem = new Filesystem();
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
      $directories = $this->getFiles($source_directory, '/^[0-9][0-9][0-9][0-9]$/');
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
   * Get the source directory for the given iso3 and version.
   *
   * @param string $iso3
   *   The iso3 code for which to lookup the source directory.
   * @param mixed $version
   *   The version to lookup the source directory.
   *
   * @return string
   *   The path to the source directory.
   */
  public function getSourceDirectoryPath($iso3, $version) {
    return self::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version;
  }

  /**
   * Get all ISO codes that are supported.
   *
   * @return string[]
   *   An array of iso3 codes.
   */
  public function getIsoCodes(): array {
    $directories = $this->getFiles(GeoJson::GEOJSON_SOURCE_DIR);
    return array_values(array_map(function ($directory) {
      return $directory->filename;
    }, $directories));
  }

  /**
   * Get all available versions for the given country.
   *
   * @param string $iso3
   *   The iso3 code for which to lookup the versions.
   *
   * @return array
   *   An array of versions.
   */
  public function getVersionsForIsoCode($iso3): array {
    $version_directories = $this->getFiles(GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3);
    $versions = array_map(function ($version_directory) {
      return $version_directory->filename;
    }, $version_directories);
    return array_values(array_reverse($versions));
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

  /**
   * Save an uploaded geojson version archive.
   *
   * @param string $iso3
   *   The country code.
   * @param string $version
   *   The version.
   * @param string $filepath
   *   The path of the uploaded file.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function saveUploadArchive(string $iso3, string $version, string $filepath): bool {
    $zip = new \ZipArchive();
    if (!$zip->open($filepath) === TRUE) {
      return FALSE;
    }
    $status = $this->extractZipArchive($zip, self::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version, $iso3);
    $zip->close();
    Cache::invalidateTags($this->getCacheTags($iso3, $version));
    return $status;
  }

  /**
   * Validate an archive file for the given country iso.
   *
   * @param string $filepath
   *   The filepath of the file to validate.
   * @param string $iso3
   *   The country code.
   *
   * @return array
   *   An array of errors if any.
   */
  public function validateArchiveFile(string $filepath, string $iso3): array {
    $temp_name = 'geojson-validate-' . $this->fileSystem->basename($filepath);
    $errors = [];
    $zip = new \ZipArchive();
    $status = $zip->open($filepath);
    if ($status !== TRUE) {
      $errors[] = (string) $this->t('Unable to open the archive file.');
      return $errors;
    }
    $temp_dir = 'temporary://' . $temp_name;

    $status = $this->extractZipArchive($zip, $temp_dir, $iso3, $errors);
    if ($status !== TRUE) {
      return $errors;
    }
    // Check for expected filenames.
    $files = $this->getFiles($temp_dir);
    $filenames = array_map(function ($file) {
      return $file->filename;
    }, $files);
    $allowed = $this->getExpectedFilenamesForArchive($iso3);
    $unsupported_files = array_diff($filenames, $allowed);
    if (!empty($unsupported_files)) {
      $errors[] = (string) $this->t('There are unsupported files in the archive: @unsupported_files', [
        '@unsupported_files' => implode(', ', $unsupported_files),
      ]);
    }

    $zip->close();
    return $errors;
  }

  /**
   * Extract the given zip archive.
   *
   * This is mainly used to do some sanity checks on the files to be extracted
   * and to support both archives having the geojson files in the root as well
   * as having the geojson files inside a subdirectory.
   *
   * @param \ZipArchive $zip
   *   The archive to extract.
   * @param string $base_dir
   *   The basedir where to extrac the files.
   * @param string $iso3
   *   The country code.
   * @param array|null $errors
   *   Optional array to collect errors.
   *
   * @return bool
   *   TRUE if extraction was successful, FALSE otherwise.
   */
  private function extractZipArchive(\ZipArchive $zip, string $base_dir, string $iso3, ?array &$errors = []): bool {
    $country_shapefile_name = $iso3 . '_0.geojson';
    $country_shapefile_index = $zip->locateName($country_shapefile_name, \ZipArchive::FL_NODIR);
    if ($country_shapefile_index === FALSE) {
      // The main country shape file has not been found.
      $errors[] = (string) $this->t('The main country shapefile @filename is missing', [
        '@filename' => $country_shapefile_name,
      ]);
      return FALSE;
    }
    $country_shapefile = $zip->getNameIndex($country_shapefile_index);

    // See if this is inside a subfolder.
    $directories = explode('/', $country_shapefile);
    array_pop($directories);
    if (count($directories) > 1) {
      // Multiple nested subdirectories are not supported.
      $errors[] = (string) $this->t('Multiple levels of directory hierarchy in the archive file are not supported');
      return FALSE;
    }
    $strip_path_components = reset($directories);

    for( $i = 0; $i < $zip->numFiles; $i++ ){
      $original_filename = $zip->getNameIndex($i);
      $filename = $strip_path_components ? str_replace($strip_path_components . '/', '', $original_filename) : $original_filename;
      if (empty($filename) || !preg_match('/^([adm\d\/|' . $iso3 . ']).*\.geojson$/', $filename)) {
        continue;
      }
      $base_dir = trim($base_dir, '/') . '/';
      $zip->extractTo($base_dir, $original_filename);

      if ($strip_path_components) {
        $original_filepath = $base_dir . $original_filename;
        $target_filepath = $base_dir . $filename;
        $target_dir = $this->fileSystem->dirname($target_filepath);
        if (!is_dir($target_dir)) {
          $this->fileSystem->mkdir($target_dir, NULL, TRUE);
        }
        $this->fileSystem->move($original_filepath, $target_filepath);
      }
    }
    if ($strip_path_components) {
      $this->fileSystem->deleteRecursive($base_dir . $strip_path_components);
    }
    return TRUE;
  }

  private function getExpectedFilenamesForArchive(string $iso3): array {
    return [
      $iso3 . '_0.geojson',
      $iso3 . '_0.min.geojson',
      'adm1',
      'adm2',
      'adm3',
    ];
  }

  /**
   * Rename a geojson version directory.
   *
   * @param string $iso3
   *   The country code.
   * @param string $version
   *   The existing version.
   * @param string $new_version
   *   The new version.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function renameVersion(string $iso3, string $version, string $new_version): bool {
    $origin = self::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version;
    $target = self::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;
    try {
      $this->symfonyFileSystem->rename($origin, $target);
    }
    catch (IOException $e) {
      return FALSE;
    }
    Cache::invalidateTags($this->getCacheTags($iso3, $version));
    return TRUE;
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
    return $this->fileSystem->deleteRecursive($this->getSourceDirectoryPath($iso3, $version));
  }

  /**
   * Get the cache tags for an iso code and verions (optional)
   *
   * @param string $iso3
   *   The country code.
   * @param string|null $version
   *   Optional geojson version.
   *
   * @return array
   *   An array of cache tags.
   */
  public function getCacheTags(string $iso3, ?string $version = NULL): array {
    return Cache::buildTags('ghi_geojson', array_filter([
      'geojson-' . $iso3,
      $version ? 'geojson-' . $iso3 . '-' . $version : NULL,
    ]));
  }

}