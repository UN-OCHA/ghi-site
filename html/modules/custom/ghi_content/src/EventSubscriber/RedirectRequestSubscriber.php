<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirect subscriber to check for possible redirects of contextual urls.
 */
class RedirectRequestSubscriber implements EventSubscriberInterface {

  use ContentPathTrait;

  /**
   * Handles the redirect if any found.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelRequestCheckRedirect(RequestEvent $event) {
    $request = clone $event->getRequest();
    $path = $request->getPathInfo();
    $redirect_path = NULL;
    $article = $this->getArticleNodeFromPath($path);
    $document = $this->getDocumentNodeFromPath($path);
    if ($article && $article->access() && $article->toUrl()->toString() != $path) {
      $redirect_path = $article->toUrl()->toString();
    }
    elseif ((!$article || !$article->access()) && $document && $document->access() && $document->toUrl()->toString() != $path) {
      $redirect_path = $document->toUrl()->toString();
    }

    if ($redirect_path) {
      $response = new TrustedRedirectResponse($redirect_path);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This needs to run before RouterListener::onKernelRequest(), which has
    // a priority of 32. Otherwise, that aborts the request if no matching
    // route is found.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestCheckRedirect', 34];
    return $events;
  }

}
