<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for lazy-loaded map modal contents in configuration preview.
 */
class PreviewMapModalDataController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * The expirable key/value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentAccount;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->keyValueExpirableFactory = $container->get('keyvalue.expirable');
    $instance->currentAccount = $container->get('current_user');
    return $instance;
  }

  /**
   * Returns the modal content for a previewed map location.
   *
   * @param string $token
   *   The token referencing the stored preview modal data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The modal content response.
   */
  public function data(string $token): JsonResponse {
    $data_index = $this->currentRequest->query->get('data_index');
    $object_id = $this->currentRequest->query->get('object_id');
    $variant_id = $this->currentRequest->query->get('variant_id') ?: 'base';
    if ($data_index === NULL || $object_id === NULL) {
      throw new NotFoundHttpException();
    }

    $store = $this->keyValueExpirableFactory->get(PlanAttachmentMap::CONFIGURATION_PREVIEW_MODAL_COLLECTION);
    $entry = $store->get(implode(':', [$token, $data_index, $variant_id]));
    if (empty($entry)) {
      throw new NotFoundHttpException();
    }
    if (($entry['uid'] ?? NULL) !== (int) $this->currentAccount->id()) {
      throw new AccessDeniedHttpException();
    }

    $modal_content = $entry['modal_contents'][(string) $object_id] ?? NULL;
    if ($modal_content === NULL) {
      throw new NotFoundHttpException();
    }

    $response = new JsonResponse($modal_content);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

}
