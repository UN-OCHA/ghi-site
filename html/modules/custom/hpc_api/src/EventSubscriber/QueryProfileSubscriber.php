<?php

namespace Drupal\hpc_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

use Drupal\hpc_api\Helpers\QueryHelper;

/**
 * Dump profiling information at the end of a request.
 *
 * This will only work if the debug_tools modules from
 * https://www.drupal.org/sandbox/berliner/1836434 is installed.
 */
class QueryProfileSubscriber implements EventSubscriberInterface {

  /**
   * Optional debug service.
   *
   * @var object
   */
  private $debugLogger;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::TERMINATE] = 'logQueryProfileForRequest';
    return $events;
  }

  /**
   * Dump the call times tgether with memory usage.
   *
   * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
   *   The post response event.
   */
  public function logQueryProfileForRequest(PostResponseEvent $event) {
    if (!$this->debugLogger || !is_object($this->debugLogger) || !method_exists($this->debugLogger, 'toFile')) {
      return NULL;
    }
    $call_times = QueryHelper::endpointCallTimeStorage();
    if (empty($call_times)) {
      return;
    }

    $this->debugLogger->toFile($call_times);
  }

  /**
   * Set the debug logger if available.
   *
   * @param object $debug_logger
   *   The log service to use for query profiles per request.
   */
  public function setDebugLogger(object $debug_logger) {
    $this->debugLogger = $debug_logger;
  }

}
