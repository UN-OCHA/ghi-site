<?php

namespace Drupal\ghi_embargoed_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for embargoed access.
 */
class EmbargoedAccessController extends ControllerBase {

  /**
   * The ServerBag object from the current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The embargoed access manager.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->requestStack = $container->get('request_stack');
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
    return $instance;
  }

  /**
   * Callback for toggle the protection status.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to toggle the protection status.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect object.
   */
  public function toggleStatus(NodeInterface $node) {
    try {
      if ($referrer = $this->requestStack->getCurrentRequest()->server->get('HTTP_REFERER')) {
        $redirect_url = Url::fromUri($referrer, ['absolute' => TRUE])->getUri();
      }
      else {
        $redirect_url = $node->toUrl()->toString();
      }
    }
    catch (\Exception $e) {
      $redirect_url = Url::fromRoute('<front>')->setAbsolute()->toString();
    }

    $t_args = [
      '@title' => $node->label(),
    ];
    if ($this->embargoedAccessManager->isProtected($node)) {
      $this->embargoedAccessManager->unprotectNode($node);
      $this->messenger()->addStatus($this->t('Password protection has been removed from <em>@title</em>', $t_args));
    }
    else {
      $this->embargoedAccessManager->protectNode($node);
      $this->messenger()->addStatus($this->t("<em>@title</em> has been password protected", $t_args));
    }

    return new RedirectResponse($redirect_url);
  }

}
