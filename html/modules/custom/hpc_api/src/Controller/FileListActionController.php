<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for a geojson files.
 */
class FileListActionController extends ControllerBase {

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  public $fileSystem;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $stack;

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->fileSystem = $container->get('file_system');
    $instance->stack = $container->get('request_stack');
    $instance->router = $container->get('router.no_access_checks');
    $instance->container = $container;
    return $instance;
  }

  /**
   * Purge files from one of the file reports.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   */
  public function purgeFiles() {
    $redirect = $this->redirect('hpc_api.reports.files.data_source');

    $referer = $this->stack->getCurrentRequest()->headers->get('referer');
    $allowed = [
      'data-source',
      'icons',
      'geojson',
    ];

    if (empty($referer)) {
      return $redirect;
    }
    $parts = explode('/', $referer);
    $type = end($parts);
    if (!in_array($type, $allowed)) {
      return $redirect;
    }

    // Get the route match to find the controller responsible for collecting
    // the files.
    $original_url = $referer;
    try {
      $match = $this->router->match($original_url);
    }
    catch (\Exception $e) {
      // Just fail silently.
      return $redirect;
    }

    /** @var \Symfony\Component\Routing\Route $route */
    [$controller] = explode(':', $match['_controller']);
    $original_route_name = $match['_route'];
    $instance = $controller::create($this->container);
    $files = $instance->getFiles();
    foreach ($files as $file) {
      $this->fileSystem->delete($file->uri);
    }

    $this->messenger()->addStatus($this->t('@count files have been deleted.', [
      '@count' => count($files),
    ]));
    return $this->redirect($original_route_name);
  }

}
