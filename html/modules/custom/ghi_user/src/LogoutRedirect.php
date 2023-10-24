<?php

namespace Drupal\ghi_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\ghi_subpages\Entity\SubpageNode;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * LogoutRedirect service class.
 */
class LogoutRedirect {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Site config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new ActiveLinkResponseFilter instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The path matcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(RequestStack $request_stack, PathMatcherInterface $path_matcher, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->request = $request_stack->getCurrentRequest();
    $this->pathMatcher = $path_matcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('system.site');
  }

  /**
   * Get the redirect url.
   *
   * @return \Drupal\Core\Url|null
   *   The redirect url or NULL if not applicable.
   */
  public function getRedirectUrl() {
    try {
      $current_url = Url::createFromRequest($this->request);
    }
    catch (\Exception $e) {
      return NULL;
    }
    if ($this->pathMatcher->isFrontPage() || $current_url->toString() == $this->config->get('page.front')) {
      // Redirect to front page is the default. We need to double check because
      // PathMather::isFrontPage() will return false if the frontpage is set to
      // an alias instead of an internal path.
      return NULL;
    }
    if ($current_url->access(new AnonymousUserSession())) {
      // The anonymous user has access to the current page so we can use that
      // as the redirect destination.
      return $current_url;
    }
    // Otherwise see if the current page is a node page and if that node is a
    // subpage of another node that we can redirect to.
    $params = $current_url->getRouteParameters();
    $node_id = $params['node'] ?? NULL;
    $node = $node_id ? $this->entityTypeManager->getStorage('node')->load($node_id) : NULL;
    $parent = $node && $node instanceof SubpageNode ? $node->getParentNode() : NULL;
    if ($parent && $parent->access('view')) {
      return $parent->toUrl();
    }

    // Nothing applies, so we bail out.
    return NULL;
  }

}
