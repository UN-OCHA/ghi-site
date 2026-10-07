<?php

namespace Drupal\ghi_blocks\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;

/**
 * Provides shared dependencies for Layout Builder migration queues.
 */
abstract class BaseQueue {

  /**
   * Public constructor.
   */
  public function __construct(protected Connection $database, protected QueueFactory $queueFactory) {}

}
