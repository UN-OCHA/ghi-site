<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

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
    if ($article && $article->access() && $article->toUrl()->toString() != $path && $this->canRedirect($path, $article)) {
      $redirect_path = $article->toUrl()->toString();
    }
    elseif ((!$article || !$article->access()) && $document && $document->access() && $document->toUrl()->toString() != $path && $this->canRedirect($path, $document)) {
      $redirect_path = $document->toUrl()->toString();
    }

    if ($redirect_path) {
      $response = new TrustedRedirectResponse($redirect_path);
      $event->setResponse($response);
    }
  }

  /**
   * Check if the given path can be redirected for the entity.
   *
   * @param string $path
   *   The path to check.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the path can be redirected, FALSE otherwise.
   */
  private function canRedirect($path, EntityInterface $entity) {
    if ($this->isEntityOperationPath($path, $entity)) {
      return FALSE;
    }
    $blacklist = [
      '/layout/discard-changes',
      '/layout/revert',
      '/layout/disable',
      '/page-elements',
      '/translations',
      '/revisions',
      '/delete',
    ];
    foreach ($blacklist as $blacklist_path) {
      if (strpos($path, $blacklist_path)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Check if the given path is any of the operation link paths for the entity.
   *
   * @param string $path
   *   The path to check.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the path is one of the operation link paths, FALSE otherwise.
   */
  private function isEntityOperationPath($path, EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\EntityListBuilderInterface $list_builder */
    $list_builder = $this->entityTypeManager->getListBuilder($entity->getEntityTypeId());
    $operations = $list_builder->getOperations($entity);
    foreach ($operations as $link) {
      if (parse_url($link['url']->toString(), PHP_URL_PATH) == $path) {
        return TRUE;
      }
    }
    return FALSE;
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
