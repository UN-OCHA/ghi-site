<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\hpc_api\FabricHealthCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the minimal Fabric health endpoint.
 */
final class FabricHealthController implements ContainerInjectionInterface {

  /**
   * Constructs a Fabric health controller.
   */
  public function __construct(private readonly FabricHealthCheck $healthCheck) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('hpc_api.fabric_health_check'));
  }

  /**
   * Reports whether a live Fabric query succeeds.
   */
  public function check(): Response {
    $status = $this->healthCheck->isAvailable() ? Response::HTTP_NO_CONTENT : Response::HTTP_SERVICE_UNAVAILABLE;
    return new Response('', $status, [
      'Cache-Control' => 'no-store, private',
    ]);
  }

}
