<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Router;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_blocks\Ajax\MapInitCommand;
use Drupal\ghi_blocks\Interfaces\LazyMapDataFragmentBlockInterface;
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
   * This is the first lazy request for a map. It returns Drupal Ajax commands
   * that replace any server-rendered shell markup and initialize the map JS.
   * Follow-up JSON-only requests use dataFragment() and modalData().
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
    $map_id = $this->currentRequest->query->get('map_id');
    if (empty($map_id)) {
      throw new NotFoundHttpException();
    }

    $access = $this->checkUriAccess($this->getCurrentUri());
    if (!$access->isAllowed()) {
      $response = new CacheableAjaxResponse();
      $response->setStatusCode(403);
      $response->addCacheableDependency($access);
      return $response;
    }

    $block = $this->getMapBlock($plugin_id, $block_uuid);
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
   * Get a lazy map data fragment.
   *
   * This endpoint returns the data slice for one map tab or variant. The slice
   * is JSON-only and is merged into the existing browser-side map settings; it
   * does not include the one-location sidebar/modal content handled below.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param string $block_uuid
   *   The block UUID.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The cacheable map data fragment response.
   */
  public function dataFragment(string $plugin_id, string $block_uuid): CacheableJsonResponse {
    $map_id = $this->currentRequest->query->get('map_id');
    $data_index = $this->currentRequest->query->get('data_index');
    $variant_id = $this->currentRequest->query->get('variant_id') ?: NULL;
    if (empty($map_id) || empty($data_index)) {
      throw new NotFoundHttpException();
    }

    $access = $this->checkUriAccess($this->getCurrentUri());
    if (!$access->isAllowed()) {
      $response = new CacheableJsonResponse([], 403);
      $response->addCacheableDependency($access);
      return $response;
    }

    $block = $this->getMapBlock($plugin_id, $block_uuid);
    if (!$block instanceof LazyMapDataFragmentBlockInterface) {
      throw new NotFoundHttpException();
    }

    $fragment = $block->buildLazyMapDataFragment($map_id, $data_index, $variant_id);
    if (!$fragment) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableJsonResponse($fragment->getData());
    $response->addCacheableDependency($access);
    $response->addCacheableDependency($fragment->getCacheability());
    return $response;
  }

  /**
   * Get lazy modal content for a map location.
   *
   * This endpoint returns exactly one location's sidebar/modal payload for the
   * active data index and variant. Keeping this separate from dataFragment()
   * prevents a loaded map slice from also loading every sidebar row.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param string $block_uuid
   *   The block UUID.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The cacheable modal content response.
   */
  public function modalData(string $plugin_id, string $block_uuid): CacheableJsonResponse {
    $map_id = $this->currentRequest->query->get('map_id');
    $data_index = $this->currentRequest->query->get('data_index');
    $object_id = $this->currentRequest->query->get('object_id');
    $variant_id = $this->currentRequest->query->get('variant_id') ?: NULL;
    if (empty($map_id) || empty($data_index) || $object_id === NULL) {
      throw new NotFoundHttpException();
    }

    $access = $this->checkUriAccess($this->getCurrentUri());
    if (!$access->isAllowed()) {
      $response = new CacheableJsonResponse([], 403);
      $response->addCacheableDependency($access);
      return $response;
    }

    $block = $this->getMapBlock($plugin_id, $block_uuid);
    if (!$block instanceof LazyMapDataFragmentBlockInterface) {
      throw new NotFoundHttpException();
    }

    $fragment = $block->buildLazyMapModalFragment($map_id, $data_index, $object_id, $variant_id);
    if (!$fragment) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableJsonResponse($fragment->getData());
    $response->addCacheableDependency($access);
    $response->addCacheableDependency($fragment->getCacheability());
    return $response;
  }

  /**
   * Get the current page URI from the query.
   *
   * @return string
   *   The current page URI.
   */
  private function getCurrentUri(): string {
    $uri = $this->currentRequest->query->get('current_uri');
    if (empty($uri)) {
      throw new NotFoundHttpException();
    }
    return $uri;
  }

  /**
   * Get the lazy map block for the current page URI.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param string $block_uuid
   *   The block UUID.
   *
   * @return object|null
   *   The block instance, if found.
   */
  private function getMapBlock(string $plugin_id, string $block_uuid): ?object {
    return BlockHelper::getBlockInstance($this->getCurrentUri(), $plugin_id, $block_uuid);
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
