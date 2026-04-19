<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for local sub-article AJAX interactions.
 */
class SubArticleController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The sub-article renderer.
   *
   * @var \Drupal\ghi_content\SubArticleRenderer
   */
  protected $subArticleRenderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->subArticleRenderer = $container->get('ghi_content.subarticle_renderer');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * Access callback for deferred local sub-article content.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The local sub-article node.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    if (!$node instanceof Article) {
      return AccessResult::forbidden();
    }
    $access = $node->access('view', $account, TRUE);
    foreach (array_filter([$node, $this->loadAccessContextNode()]) as $protected_node) {
      if (!$protected_node instanceof ContentBase || !$protected_node->isProtected()) {
        continue;
      }
      // The AJAX route is independent of the original page route, so repeat
      // protected access checks for both the sub-article and the parent context
      // node. These decisions can be backed by a short-lived password/session
      // grant, so do not let route access cache reuse them across users.
      $access = $access->andIf(AccessResult::allowedIf($protected_node->protectedAccess())
        ->addCacheableDependency($protected_node)
        ->setCacheMaxAge(0));
    }
    return $access;
  }

  /**
   * Load the deferred local sub-article content.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The local sub-article node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function loadDeferredContent(NodeInterface $node, Request $request) {
    $offset = max(0, (int) $request->query->get('offset', 0));
    $context_node = $this->loadContextNode($request);
    $contexts = $this->loadContexts($request);
    $build = $node instanceof Article ? $this->subArticleRenderer->build($node, $context_node, $contexts, $offset) : [];

    $placeholder_id = Html::getId($request->query->get('placeholder_id') ?: 'ghi-subarticle-deferred-' . $node->id());
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $placeholder_id, $build));
    return $response;
  }

  /**
   * Load the optional parent context node from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The context node, or NULL.
   */
  private function loadContextNode(Request $request) {
    $context_node_id = $request->query->get('context_node');
    if (!$context_node_id) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($context_node_id);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Load the optional parent context node during route access checks.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The context node, or NULL.
   */
  private function loadAccessContextNode() {
    $request = $this->requestStack?->getCurrentRequest();
    return $request ? $this->loadContextNode($request) : NULL;
  }

  /**
   * Load context values passed from the original sub-article render.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Contexts keyed by context name.
   */
  private function loadContexts(Request $request) {
    $contexts = [];
    foreach ($request->query->all('contexts') as $key => $encoded_context) {
      $parts = explode(':', $encoded_context);
      if (count($parts) != 3 || $parts[0] != 'entity') {
        continue;
      }
      $entity = $this->entityTypeManager->getStorage($parts[1])->load($parts[2]);
      if (!$entity) {
        continue;
      }
      $contexts[$key] = EntityContext::fromEntity($entity);
    }
    return $contexts;
  }

}
