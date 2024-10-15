<?php

namespace Drupal\ghi_user\EventSubscriber;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Dump profiling information at the end of a request.
 */
class RemoveCheckLoggedIn implements EventSubscriberInterface {

  /**
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs an AutologoutSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   */
  public function __construct(AccountInterface $account) {
    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST] = ['checkRequest', 1000];
    return $events;
  }

  /**
   * Remove "check_logged_in" query arg if necessary.
   *
   * This is done to prevent the "your browser must accept cookies" message to
   * appear for anonymous users or after a logout.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The post response event.
   *
   * @see https://www.drupal.org/project/drupal/issues/3255711
   */
  public function checkRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $query_args = $request->query->all();
    if (array_key_exists('check_logged_in', $query_args) && $this->currentUser->isAnonymous()) {
      unset($query_args['check_logged_in']);
      try {
        $url = Url::createFromRequest($request);
      }
      catch (\Exception $e) {
        return;
      }
      $url->setOption('query', $query_args);
      $event->setResponse(new CacheableRedirectResponse($url->toString()));
    }
  }

}
