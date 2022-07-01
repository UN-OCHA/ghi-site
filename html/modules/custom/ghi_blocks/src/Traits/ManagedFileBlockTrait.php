<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;

/**
 * Trait with common helpers to get inliny with GIN LB styles..
 */
trait ManagedFileBlockTrait {

  /**
   * Persist files that have been uploaded as part of a plugin configuration.
   *
   * @param array $files
   *   The array of files to remove.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   * @param string $uuid
   *   The uuid of the block.
   */
  private function persistFiles(array $files, EntityInterface $entity, $uuid) {
    // Make files permanent and add file usage.
    $usage_type = $this->getFileUsageType($entity);
    foreach ($files as $file) {
      if ($file->isPermanent()) {
        continue;
      }
      $file->setPermanent();
      $file->save();
      $this->getFileUsageService()->add($file, 'ghi_blocks', $usage_type, $uuid);
    }
    $stored_files = $this->getStoredFiles($entity, $uuid);
    $removed_files = array_diff_key($stored_files, $files);
    if (!empty($removed_files)) {
      $this->cleanupFiles($removed_files, $entity, $uuid);
    }
  }

  /**
   * Properly remove and cleanup files that are no longer in use.
   *
   * @param array $files
   *   The array of files to remove.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   * @param string $uuid
   *   The uuid of the block.
   */
  private function cleanupFiles(array $files, EntityInterface $entity, $uuid) {
    if (empty($files)) {
      return;
    }
    // Delete file usage and delete file.
    $usage_type = $this->getFileUsageType($entity);
    foreach ($files as $file) {
      $this->getFileUsageService()->delete($file, 'ghi_blocks', $usage_type, $uuid);
      $file->delete();
    }
  }

  /**
   * Get the files stored for this block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   * @param string $uuid
   *   The uuid of the block.
   *
   * @return \Drupal\file\Entity\File[]
   *   An array of file objects.
   */
  private function getStoredFiles(EntityInterface $entity, $uuid) {
    $usage_type = $this->getFileUsageType($entity);
    $result = $this->getDatabaseService()->select('file_usage', 'f')
      ->fields('f', ['fid'])
      ->condition('module', 'ghi_blocks')
      ->condition('type', $usage_type)
      ->condition('id', $uuid)
      ->execute();
    $files = [];
    foreach ($result as $record) {
      $files[$record->fid] = File::load($record->fid);
    }
    return $files;
  }

  /**
   * Get the type string used in the `file_usage' table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   *
   * @return string
   *   Get a string that describes the file usage type.
   */
  private function getFileUsageType(EntityInterface $entity) {
    return implode(':', [
      $entity->getEntityTypeId(),
      $entity->id(),
    ]);
  }

  /**
   * Get the file usage service.
   *
   * @return \Drupal\file\FileUsage\DatabaseFileUsageBackend
   *   A file usage service object.
   */
  private function getFileUsageService() {
    return \Drupal::service('file.usage');
  }

  /**
   * Get the database service.
   *
   * @return \Drupal\Core\Database\Connection
   *   A database connection object.
   */
  private function getDatabaseService() {
    return \Drupal::service('database');
  }

}
