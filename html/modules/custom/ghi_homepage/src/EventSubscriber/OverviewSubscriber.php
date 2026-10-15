<?php

namespace Drupal\ghi_homepage\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Requires a published homepage for explicitly requested overview years.
 */
class OverviewSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an overview subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Rejects overview years without a published homepage.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$this->isOverviewRequest($request)) {
      return;
    }

    $year = $request->attributes->get('year');
    // Leave the block's default-year selection unchanged for /overview.
    if ($year === NULL) {
      return;
    }

    $homepages = [];
    if (filter_var($year, FILTER_VALIDATE_INT) !== FALSE) {
      // Homepage::access() deliberately hides the standalone node page from
      // anonymous users. The public overview depends on publication instead.
      $homepages = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => Homepage::BUNDLE,
        'field_year' => $year,
        'status' => NodeInterface::PUBLISHED,
      ]);
    }
    if (!$homepages) {
      throw new CacheableNotFoundHttpException($this->getCacheability());
    }
  }

  /**
   * Invalidates successful responses too when homepages change publication.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    if ($this->isOverviewRequest($event->getRequest()) && $response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency($this->getCacheability());
    }
  }

  /**
   * Identifies all variants of the public homepage, excluding node routes.
   */
  private function isOverviewRequest(Request $request): bool {
    $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    return $route?->getDefault('_page_manager_page') === 'homepage';
  }

  /**
   * Gets cache metadata for the existence of a published homepage.
   */
  private function getCacheability(): CacheableMetadata {
    // A list tag also invalidates a cached 404 when a homepage is created.
    return (new CacheableMetadata())
      ->setCacheTags(['node_list:homepage'])
      ->setCacheContexts(['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Validate after routing (32), before Dynamic Page Cache (27).
      KernelEvents::REQUEST => ['onRequest', 30],
      // Add cache metadata before Dynamic Page Cache stores the response (7).
      KernelEvents::RESPONSE => ['onResponse', 10],
    ];
  }

}
