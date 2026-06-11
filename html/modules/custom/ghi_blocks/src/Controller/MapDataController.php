<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Router;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_blocks\Ajax\MapInitCommand;
use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\hpc_common\Helpers\BlockHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for lazy-loaded map data.
 */
class MapDataController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected AccessManagerInterface $accessManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentAccount;

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected Router $router;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->accessManager = $container->get('access_manager');
    $instance->currentAccount = $container->get('current_user');
    $instance->router = $container->get('router.no_access_checks');
    return $instance;
  }

  /**
   * Get the map payload for a lazy map block.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param string $block_uuid
   *   The block UUID.
   *
   * @return \Drupal\Core\Cache\CacheableAjaxResponse
   *   The cacheable map data response.
   */
  public function data(string $plugin_id, string $block_uuid): CacheableAjaxResponse {
    $uri = $this->currentRequest->query->get('current_uri');
    $map_id = $this->currentRequest->query->get('map_id');
    if (empty($uri) || empty($map_id)) {
      throw new NotFoundHttpException();
    }

    $access = $this->checkUriAccess($uri);
    if (!$access->isAllowed()) {
      $response = new CacheableAjaxResponse();
      $response->setStatusCode(403);
      $response->addCacheableDependency($access);
      return $response;
    }

    $block = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block instanceof LazyMapBlockInterface) {
      throw new NotFoundHttpException();
    }

    $payload = $block->buildLazyMapPayload($map_id);
    $response = new CacheableAjaxResponse();
    $response->addCacheableDependency($access);
    if ($payload->isEmpty()) {
      $response->addCommand(new RemoveCommand('#' . Html::getId('block-' . $block_uuid)));
      $response->addCacheableDependency($payload->getCacheability());
      return $response;
    }

    $response->setAttachments($payload->getAttachments());

    foreach ($payload->getHtml() as $selector => $content) {
      $response->addCommand(new HtmlCommand($selector, $content));
    }
    $response->addCommand(new MapInitCommand($payload->getMap()));

    $response->addCacheableDependency($payload->getCacheability());
    return $response;
  }

  /**
   * Check access for the page URI used to rebuild the lazy map block.
   *
   * @param string $uri
   *   The current page URI supplied by the lazy map request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The route access result with cacheability metadata.
   */
  protected function checkUriAccess(string $uri): AccessResultInterface {
    $request = Request::create($uri);
    try {
      // The endpoint needs no-access matching only to identify the page route.
      // Access is checked explicitly below before any map payload is built.
      $request->attributes->add($this->router->matchRequest($request));
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException();
    }

    return $this->accessManager->checkRequest($request, $this->currentAccount, TRUE);
  }

}
