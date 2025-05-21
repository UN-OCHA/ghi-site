<?php

namespace Drupal\hpc_api\EventSubscriber;

use Drupal\hpc_api\Helpers\ProfileHelper;
use Drupal\hpc_api\Helpers\QueryHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
    $events[KernelEvents::TERMINATE][] = ['logQueryProfileForRequest'];
    $events[KernelEvents::TERMINATE][] = ['logCustomProfileSummaryForRequest'];
    return $events;
  }

  /**
   * Dump the call times tgether with memory usage.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The post response event.
   */
  public function logQueryProfileForRequest(TerminateEvent $event) {
    if (!$this->debugLogger || !is_object($this->debugLogger) || !method_exists($this->debugLogger, 'toFile')) {
      return NULL;
    }
    $call_times = QueryHelper::endpointCallTimeStorage();
    if (!empty($call_times)) {
      arsort($call_times);
      $this->debugLogger->toFile($call_times);
    }
  }

  /**
   * Dump the call times together with memory usage.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The post response event.
   */
  public function logCustomProfileSummaryForRequest(TerminateEvent $event) {
    if (!$this->debugLogger || !is_object($this->debugLogger) || !method_exists($this->debugLogger, 'toFile')) {
      return NULL;
    }
    $profile_summary = ProfileHelper::profileSummaryFormatted();
    if (!empty($profile_summary)) {
      $this->debugLogger->toFile($profile_summary);
    }
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
