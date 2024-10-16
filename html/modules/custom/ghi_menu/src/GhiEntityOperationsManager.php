<?php

namespace Drupal\ghi_menu;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;

/**
 * Manager service class for entity operations.
 */
class GhiEntityOperationsManager {

  use StringTranslationTrait;

  /**
   * The publish content access service.
   *
   * @var \Drupal\publishcontent\Access\PublishContentAccess
   */
  protected $publishContentAccess;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a section create form.
   */
  public function __construct(PublishContentAccess $publish_content_access, CsrfTokenGenerator $csrf_token, RedirectDestinationInterface $redirect_destination, AccountProxyInterface $user) {
    $this->publishContentAccess = $publish_content_access;
    $this->csrfToken = $csrf_token;
    $this->redirectDestination = $redirect_destination;
    $this->currentUser = $user;
  }

  /**
   * Get the operations links for the given subpage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   An array of operations links.
   */
  public function getOperationLinks(NodeInterface $node) {
    $links = [];

    // The token for the publishing links need to be generated manually here.
    $token = $this->csrfToken->get('node/' . $node->id() . '/toggleStatus');

    $destination = $this->redirectDestination->getAsArray();

    if ($this->publishContentAccess->access($this->currentUser, $node)->isAllowed()) {
      $route_args = ['node' => $node->id()];
      $options = [
        'query' => [
          'token' => $token,
        ] + $destination,
      ];
      $links['toggle_status'] = [
        'title' => $node->isPublished() ? $this->t('Unpublish') : $this->t('Publish'),
        'url' => Url::fromRoute('entity.node.publish', $route_args, $options),
        'weight' => 51,
      ];
    }
    return $links;
  }

}
