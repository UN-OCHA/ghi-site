<?php

namespace Drupal\hpc_api\Traits;

/**
 * Trait for helping with date time strings.
 */
trait DateTimeTrait {

  /**
   * Reformat a date for internal use in the format Y-m-d.
   *
   * @param string $date
   *   The original date string.
   *
   * @return string
   *   The reformatted string.
   */
  private function reformatDate(string $date): string {
    $datetime = new \DateTime($date, new \DateTimeZone('UTC'));
    return $datetime->format('Y-m-d');
  }

  /**
   * Get a timestamp from a date.
   *
   * @param string $date
   *   The original date string.
   *
   * @return int
   *   The timestamp.
   */
  private function getTimestamp(string $date): string {
    $datetime = new \DateTime($date, new \DateTimeZone('UTC'));
    return $datetime->getTimestamp();
  }

}
