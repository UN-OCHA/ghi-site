<?php

namespace Drupal\hpc_downloads\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Class DownloadStatusUpdate.
 */
class DownloadStatusUpdateCommand implements CommandInterface {

  /**
   * The download status string.
   *
   * @var string
   */
  protected $status;

  /**
   * Constructs a SettingsCommand object.
   *
   * @param string $status
   *   The download status to set.
   */
  public function __construct($status) {
    $this->status = $status;
  }

  /**
   * Render custom ajax command.
   *
   * @return array
   *   Array containing AJAX command and download id.
   */
  public function render() {
    return [
      'command' => 'setDownLoadStatus',
      'status' => $this->status,
    ];
  }

}
