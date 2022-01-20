<?php

namespace Drupal\hpc_downloads\Helpers;

use Drupal\Core\File\Exception\FileException;

/**
 * Helper class fir file actions.
 */
class FileHelper {

  /**
   * Delete the given file.
   *
   * @param string $file_path
   *   The file path to delete.
   */
  public static function deleteFile($file_path) {
    try {
      \Drupal::service('file_system')->delete($file_path);
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

  /**
   * Move the given source file to its destination.
   */
  public static function moveFile($source, $destination) {
    try {
      return \Drupal::service('file_system')->move($source, $destination);
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

  /**
   * Save the data to the file.
   */
  public static function saveData($data, $file_uri) {
    try {
      return \Drupal::service('file_system')->saveData($data, $file_uri);
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

}
