<?php

namespace Drupal\ghi_embargoed_access\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RedirectDestination;
use Drupal\Core\Session\AccountProxy;
use Drupal\path_alias\AliasManager;
use Drupal\protected_pages\EventSubscriber\ProtectedPagesSubscriber;
use Drupal\protected_pages\ProtectedPagesStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a global switch for the protected pages service.
 */
class GhiEmbargoedAccessEventSubscriber extends ProtectedPagesSubscriber implements EventSubscriberInterface {

  /**
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ProtectedPagesSubscriber.
   *
   * @param \Drupal\path_alias\AliasManager $aliasManager
   *   The path alias manager.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The account proxy service.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path stack service.
   * @param \Drupal\Core\Routing\RedirectDestination $destination
   *   The redirect destination service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\protected_pages\ProtectedPagesStorage $protectedPagesStorage
   *   The request stack service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The cache kill switch service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(AliasManager $aliasManager, AccountProxy $currentUser, CurrentPathStack $currentPathStack, RedirectDestination $destination, RequestStack $requestStack, ProtectedPagesStorage $protectedPagesStorage, KillSwitch $pageCacheKillSwitch, ConfigFactoryInterface $config_factory) {
    parent::__construct($aliasManager, $currentUser, $currentPathStack, $destination, $requestStack, $protectedPagesStorage, $pageCacheKillSwitch);

    $this->configFactory = $config_factory;
  }

  /**
   * Redirects user to protected page login screen.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function checkProtectedPage(ResponseEvent $event) {
    if (!$this->configFactory->get('ghi_embargoed_access.settings')->get('enabled')) {
      return NULL;
    }
    return parent::checkProtectedPage($event);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkProtectedPage'];
    return $events;
  }

}
