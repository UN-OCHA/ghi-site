<?php

namespace Drupal\ghi_content\RemoteSource;

/**
 * Provides remote refresh settings for a remote source.
 */
interface RemoteRefreshSourceInterface {

  /**
   * Get the shared secret used to verify refresh notifications.
   *
   * @return string|null
   *   The shared secret or NULL if none is configured.
   */
  public function getRemoteRefreshWebhookSecret(): ?string;

  /**
   * Get the maximum age for signed refresh notifications.
   *
   * @return int
   *   The maximum age in seconds.
   */
  public function getRemoteRefreshSignatureTtl(): int;

  /**
   * Get the maximum allowed refresh notification body size.
   *
   * @return int
   *   The maximum body size in bytes.
   */
  public function getRemoteRefreshMaxBodySize(): int;

}
