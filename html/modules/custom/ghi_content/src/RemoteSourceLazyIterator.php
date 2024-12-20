<?php

namespace Drupal\ghi_content;

use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Lazy iterator for remote sources.
 */
class RemoteSourceLazyIterator implements \Iterator {

  /**
   * The current index.
   *
   * @var int
   */
  private int $index = 0;

  /**
   * The array of ids to fetch.
   *
   * @var array
   */
  private array $ids;

  /**
   * The type of item to fetch.
   *
   * @var string
   */
  private string $type;

  /**
   * The remote source from which to fetch the data.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  private RemoteSourceInterface $remoteSource;

  /**
   * Public constructor for the iterator.
   *
   * @param \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source
   *   The remote source.
   * @param string $type
   *   The type of content as a string.
   * @param array $tags
   *   An optional array of tags to limit the result set.
   */
  public function __construct(RemoteSourceInterface $remote_source, $type, $tags = NULL) {
    $this->remoteSource = $remote_source;
    $this->type = $type;
    $this->ids = $this->remoteSource->getImportIds($type, $tags);
  }

  /**
   * Get the current item.
   *
   * @return array
   *   The raw data.
   */
  public function current(): mixed {
    return $this->remoteSource->getImportData($this->type, $this->ids[$this->index]);
  }

  /**
   * Get the next item.
   */
  public function next(): void {
    $this->index++;
  }

  /**
   * Get the current key.
   *
   * @return int
   *   The current key.
   */
  public function key(): int {
    return $this->index;
  }

  /**
   * Validate the current data.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function valid(): bool {
    return isset($this->ids[$this->index]);
  }

  /**
   * Rewind to start.
   */
  public function rewind(): void {
    $this->index = 0;
  }

}
