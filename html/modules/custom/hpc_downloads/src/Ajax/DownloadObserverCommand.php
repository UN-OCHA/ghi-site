<?php

namespace Drupal\hpc_downloads\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Class DownloadObserver.
 */
class DownloadObserverCommand implements CommandInterface {

  /**
   * The download id to observe.
   *
   * @var int
   */
  protected $downloadId;

  /**
   * Constructs a SettingsCommand object.
   *
   * @param int $download_id
   *   The download id that should be observed.
   */
  public function __construct($download_id) {
    $this->downloadId = $download_id;
  }

  /**
   * Render custom ajax command.
   *
   * @return array
   *   Array containing AJAX command and download id.
   */
  public function render() {
    return [
      'command' => 'startDownloadObserver',
      'download_id' => $this->downloadId,
    ];
  }

}
