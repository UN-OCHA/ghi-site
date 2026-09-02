<?php

namespace Drupal\hpc_remote_data_cache;

/**
 * Value object describing the result of a remote data refresh.
 */
class RemoteDataCacheRefreshResult {

  /**
   * Constructs a remote data cache refresh result.
   *
   * @param bool $success
   *   Whether the refresh succeeded.
   * @param mixed $data
   *   The refreshed data.
   * @param string|null $error
   *   The refresh error, if any.
   */
  private function __construct(
    private readonly bool $success,
    private readonly mixed $data = NULL,
    private readonly ?string $error = NULL,
  ) {}

  /**
   * Create a successful refresh result.
   *
   * @param mixed $data
   *   The refreshed data.
   *
   * @return static
   *   The refresh result.
   */
  public static function success(mixed $data): static {
    return new static(TRUE, $data);
  }

  /**
   * Create a failed refresh result.
   *
   * @param string $error
   *   The refresh error.
   *
   * @return static
   *   The refresh result.
   */
  public static function failure(string $error): static {
    return new static(FALSE, NULL, $error);
  }

  /**
   * Whether the refresh succeeded.
   *
   * @return bool
   *   TRUE if the refresh succeeded, FALSE otherwise.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Get the refreshed data.
   *
   * @return mixed
   *   The refreshed data.
   */
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * Get the refresh error.
   *
   * @return string|null
   *   The refresh error, if any.
   */
  public function getError(): ?string {
    return $this->error;
  }

}
