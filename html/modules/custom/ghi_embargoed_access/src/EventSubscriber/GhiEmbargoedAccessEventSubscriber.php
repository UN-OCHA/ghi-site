<?php

namespace Drupal\ghi_embargoed_access\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RedirectDestination;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\path_alias\AliasManager;
use Drupal\protected_pages\EventSubscriber\ProtectedPagesSubscriber;
use Drupal\protected_pages\ProtectedPagesStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a global switch for the protected pages service.
 */
class GhiEmbargoedAccessEventSubscriber extends ProtectedPagesSubscriber implements EventSubscriberInterface {

  use ContentPathTrait;

  /**
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

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
   * @param \Drupal\ghi_embargoed_access\EmbargoedAccessManager $embargoed_access_manager
   *   The embargoed access manager service.
   */
  public function __construct(AliasManager $aliasManager, AccountProxy $currentUser, CurrentPathStack $currentPathStack, RedirectDestination $destination, RequestStack $requestStack, ProtectedPagesStorage $protectedPagesStorage, KillSwitch $pageCacheKillSwitch, ConfigFactoryInterface $config_factory, EmbargoedAccessManager $embargoed_access_manager) {
    parent::__construct($aliasManager, $currentUser, $currentPathStack, $destination, $requestStack, $protectedPagesStorage, $pageCacheKillSwitch);

    $this->configFactory = $config_factory;
    $this->embargoedAccessManager = $embargoed_access_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkProtectedPage'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function checkProtectedPage(ResponseEvent $event) {
    if (!$this->configFactory->get('ghi_embargoed_access.settings')->get('enabled')) {
      return NULL;
    }

    // We don't rely on the original logic from
    // ProtectedPagesSubscriber::checkProtectedPage because it prevents access
    // from administrative subpages of a node too. I couldn't find a ticket in
    // https://www.drupal.org/project/issues/protected_pages relating to that
    // though.
    // We do the first part in their logic which looks correct and does what it
    // should, but we omit the second part of it that is way too gready.
    if ($this->currentUser->hasPermission('bypass pages password protection')) {
      return;
    }
    $current_path = $this->aliasManager->getAliasByPath($this->currentPath->getPath());
    $normal_path = mb_strtolower($this->aliasManager->getPathByAlias($current_path));
    $pid = $this->protectedPagesIsPageLocked($current_path, $normal_path);
    if (!empty($pid)) {
      $this->sendAccessDenied($pid);
      return;
    }

    // Also check for possible parents that might be protected. Subpages should
    // automatically inherit the protection. This allows to protected a section
    // node in order to protect all subpages available in that node, including
    // all documents and articles that are displayed as part of the section.
    $parent_candidates = [];
    if ($document = $this->getCurrentDocumentNode()) {
      $parent_candidates[] = $document;
    }
    if ($section = $this->getCurrentSectionNode()) {
      $parent_candidates[] = $section;
    }
    if (!empty($parent_candidates)) {
      $page_node = $this->getRequest()->attributes->get('node') ?? NULL;
      foreach ($parent_candidates as $node) {
        if ($page_node === $node || !$this->embargoedAccessManager->isProtected($node)) {
          continue;
        }
        $pid = $this->embargoedAccessManager->loadProtectedPageIdForNode($node);
        if (!$this->checkAccessForPid($pid)) {
          $this->sendAccessDenied($pid);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendAccessDenied($pid) {
    if (empty($pid)) {
      return;
    }

    // We override this function in order to redirect to the exact same URL
    // that was originally requested.
    $current_path = $this->requestStack->getCurrentRequest()->getPathInfo();
    $query = \Drupal::destination()->getAsArray();
    $query['destination'] = $current_path;
    $query['protected_page'] = $pid;
    $this->pageCacheKillSwitch->trigger();
    $response = new RedirectResponse(Url::fromUri('internal:/protected-page', ['query' => $query])->toString());
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function protectedPagesIsPageLocked(string $current_path, string $normal_path) {
    $fields = ['pid'];
    $conditions = [];
    $conditions['or'][] = [
      'field' => 'path',
      'value' => $normal_path,
      'operator' => '=',
    ];
    $conditions['or'][] = [
      'field' => 'path',
      'value' => $current_path,
      'operator' => '=',
    ];
    $pid = $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);
    if (empty($pid)) {
      return FALSE;
    }
    return !$this->checkAccessForPid($pid) ? $pid : FALSE;
  }

  /**
   * Check the access to a protected page.
   *
   * @param int $pid
   *   The id of the protected page item.
   *
   * @return bool
   *   TRUE if access should be granted for the current session, FALSE
   *   otherwise.
   */
  public function checkAccessForPid($pid) {
    if (isset($_SESSION['_protected_page']['passwords'][$pid]['expire_time'])) {
      if (time() >= $_SESSION['_protected_page']['passwords'][$pid]['expire_time']) {
        unset($_SESSION['_protected_page']['passwords'][$pid]['request_time']);
        unset($_SESSION['_protected_page']['passwords'][$pid]['expire_time']);
      }
    }
    if (isset($_SESSION['_protected_page']['passwords'][$pid]['request_time'])) {
      return TRUE;
    }
    return FALSE;
  }

}
