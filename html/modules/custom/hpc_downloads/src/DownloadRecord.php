<?php

namespace Drupal\hpc_downloads;

/**
 * Class for download records.
 *
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
class DownloadRecord {

  const STATUS_NEW = 0;
  const STATUS_PENDING = 1;
  const STATUS_SUCCESS = 2;
  const STATUS_ERROR = 3;
  const STATUS_ABORTED = 4;

  /**
   * Load download records by optional conditions.
   *
   * @param array $conditions
   *   The conditions array.
   *
   * @return array
   *   An array of record objects.
   */
  public static function loadRecords(array $conditions = []) {
    $query = self::getDatabase()->select('hpc_download_processes', 'd');
    $query->fields('d');
    if (!empty($conditions)) {
      foreach ($conditions as $key => $value) {
        $query->condition($key, $value);
      }
    }
    $records = $query->execute()->fetchAllAssoc('id');
    foreach ($records as &$record) {
      if (!empty($record->options)) {
        $record->options = unserialize($record->options);
      }
    }
    return $records;
  }

  /**
   * Load a single record by condition.
   *
   * @param array $conditions
   *   The conditions array.
   *
   * @return array
   *   A record array.
   */
  public static function loadRecord(array $conditions) {
    $query = self::getDatabase()->select('hpc_download_processes', 'd');
    $query->fields('d');
    foreach ($conditions as $key => $value) {
      $query->condition($key, $value);
    }
    $record = $query->execute()->fetchAssoc();
    if (!empty($record['options'])) {
      $record['options'] = unserialize($record['options']);
    }
    return $record;
  }

  /**
   * Load a download record by it's ID.
   */
  public static function loadRecordById($id) {
    return self::loadRecord(['id' => (int) $id]);
  }

  /**
   * Create a download record.
   *
   * @param string $uri
   *   The URI for a download.
   * @param array $options
   *   An options array.
   *
   * @return array
   *   A record array.
   */
  public static function createRecord($uri, array $options) {
    // Using an insert query in our custom table.
    $options['database_target'] = self::getDatabase()->getTarget();
    $fields = [
      'url' => $uri,
      'uid' => \Drupal::currentUser()->id(),
      'options' => serialize($options),
      'started' => \Drupal::time()->getRequestTime(),
    ];
    $id = self::getDatabase()->insert('hpc_download_processes')
      ->fields($fields)
      ->execute();
    return self::loadRecordById($id);
  }

  /**
   * Update a record for a download process.
   */
  public static function updateRecord(&$record) {
    $options = !is_array($record['options']) ? unserialize($record['options']) : $record['options'];
    $options['database_target'] = self::getDatabase()->getTarget();
    $record['updated'] = \Drupal::time()->getRequestTime();
    $record['options'] = serialize($options);
    self::getDatabase()->merge('hpc_download_processes')
      ->key(['id' => $record['id']])
      ->fields($record)
      ->execute();

    // Reload the record to be sure we have the complete record.
    $record = self::loadRecordById($record['id']);
  }

  /**
   * Update a record for a download process.
   */
  public static function closeRecord(&$record, $status = DownloadRecord::STATUS_SUCCESS) {
    $options = !is_array($record['options']) ? unserialize($record['options']) : $record['options'];
    $options['database_target'] = self::getDatabase()->getTarget();
    $record['completed'] = \Drupal::time()->getRequestTime();
    $record['status'] = $status;
    $record['options'] = serialize($options);
    self::getDatabase()->merge('hpc_download_processes')
      ->key(['id' => $record['id']])
      ->fields($record)
      ->execute();
    $record = self::loadRecordById($record['id']);
  }

  /**
   * Get the current database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The current database connection object.
   */
  public static function getDatabase() {
    $database = \Drupal::database();
    return $database;
  }

}
