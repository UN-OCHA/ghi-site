<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapDataFragmentBlockInterface;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Preview\BlockPreviewStateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for rendering block configuration previews outside form state.
 */
class BlockPreviewController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The block preview state manager.
   */
  protected BlockPreviewStateManager $previewStateManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->previewStateManager = $container->get('ghi_blocks.preview_state_manager');
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * Render a block configuration preview.
   *
   * @param string $preview_state_token
   *   The token referencing the stored preview state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response replacing the preview placeholder.
   */
  public function preview(string $preview_state_token): AjaxResponse {
    $block = $this->previewStateManager->restore($preview_state_token);

    $build = $block->build();
    $preview = $build ? [
      '#theme' => 'block',
      '#attributes' => [
        'data-block-preview' => $block->getPluginId(),
      ] + ($build['#attributes'] ?? []),
      '#configuration' => $block->getConfiguration(),
      '#base_plugin_id' => $block->getBaseId(),
      '#plugin_id' => $block->getPluginId(),
      '#derivative_plugin_id' => $block->getDerivativeId(),
      '#id' => $block->getPluginId(),
      '#attached' => [
        'library' => ['ghi_blocks/block.preview'],
      ],
      'content' => $build,
    ] : ['#markup' => ''];

    $selector = '[data-block-preview-state-token="' . Html::escape($preview_state_token) . '"]';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $preview));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

  /**
   * Reload a block interaction from unsaved preview state.
   *
   * @param string $preview_state_token
   *   The token referencing the stored preview state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response replacing the rendered block.
   */
  public function reload(string $preview_state_token): AjaxResponse {
    $block = $this->previewStateManager->restore($preview_state_token);
    $block_uuid = $block->getUuid();
    if (empty($block_uuid)) {
      throw new NotFoundHttpException();
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.ghi-block-' . Html::getClass($block_uuid), $block->build()));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

  /**
   * Get a lazy map data fragment from preview block state.
   *
   * @param string $preview_state_token
   *   The token referencing the stored preview state.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The map data fragment response.
   */
  public function mapDataFragment(string $preview_state_token): JsonResponse {
    $map_id = $this->currentRequest->query->get('map_id');
    $data_index = $this->currentRequest->query->get('data_index');
    $variant_id = $this->currentRequest->query->get('variant_id') ?: NULL;
    if (empty($map_id) || ($data_index === NULL || $data_index === '')) {
      throw new NotFoundHttpException();
    }

    $block = $this->previewStateManager->restore($preview_state_token);
    if (!$block instanceof LazyMapDataFragmentBlockInterface) {
      throw new NotFoundHttpException();
    }

    $fragment = $block->buildLazyMapDataFragment($map_id, $data_index, $variant_id);
    if (!$fragment) {
      throw new NotFoundHttpException();
    }

    return $this->createJsonResponse($fragment->getData());
  }

  /**
   * Get lazy map modal content from preview block state.
   *
   * @param string $preview_state_token
   *   The token referencing the stored preview state.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The map modal content response.
   */
  public function mapModalData(string $preview_state_token): JsonResponse {
    $map_id = $this->currentRequest->query->get('map_id');
    $data_index = $this->currentRequest->query->get('data_index', MapModalContent::DEFAULT_DATA_INDEX);
    $object_id = $this->currentRequest->query->get('object_id');
    $variant_id = $this->currentRequest->query->get('variant_id') ?: NULL;
    if (empty($map_id) || ($data_index === NULL || $data_index === '') || $object_id === NULL) {
      throw new NotFoundHttpException();
    }

    $resource_key = MapModalContent::buildResourceKey($map_id, $data_index, $variant_id ?? MapModalContent::DEFAULT_VARIANT_ID);
    $modal_contents = $this->previewStateManager->getResource($preview_state_token, MapModalContent::RESOURCE_NAMESPACE, $resource_key);
    if (is_array($modal_contents)) {
      $modal_content = $modal_contents[(string) $object_id] ?? NULL;
      if ($modal_content === NULL) {
        throw new NotFoundHttpException();
      }
      return $this->createJsonResponse($modal_content);
    }

    $block = $this->previewStateManager->restore($preview_state_token);
    if (!$block instanceof LazyMapDataFragmentBlockInterface) {
      throw new NotFoundHttpException();
    }

    $fragment = $block->buildLazyMapModalFragment($map_id, $data_index, $object_id, $variant_id);
    if (!$fragment) {
      throw new NotFoundHttpException();
    }

    return $this->createJsonResponse($fragment->getData());
  }

  /**
   * Build a private response for data from unsaved preview state.
   *
   * @param array $data
   *   The response data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The private JSON response.
   */
  private function createJsonResponse(array $data): JsonResponse {
    $response = new JsonResponse($data);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

}
