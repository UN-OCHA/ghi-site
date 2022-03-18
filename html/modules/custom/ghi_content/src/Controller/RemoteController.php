<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for System routes.
 */
class RemoteController extends ControllerBase {

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  private $remoteSourceManager;

  /**
   * Public constructor.
   */
  public function __construct(RemoteSourceManager $remote_source_manager) {
    $this->remoteSourceManager = $remote_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.remote_source')
    );
  }

  /**
   * Handler for fetching plans using autocomplete.
   */
  public function autocompleteArticle(RemoteSourceInterface $remote_source, Request $request) {

    $matches = [];
    $string = $request->query->get('q');
    if (empty($string)) {
      return new JsonResponse($matches);
    }

    try {
      $articles = $remote_source->searchArticlesByTitle($string);
    }
    catch (ClientException $e) {
      // Just catch it for the moment.
    }

    if (!empty($articles)) {
      $matches = array_map(function (RemoteArticleInterface $article) {
        return [
          'value' => $article->getTitle() . ' (' . $article->getId() . ')',
          'label' => $article->getTitle(),
        ];
      }, $articles);
    }
    return new JsonResponse($matches);

  }

  /**
   * List available remote sources.
   */
  public function listRemoteSources() {
    $remote_sources = $this->remoteSourceManager->getDefinitions();
    $header = [
      $this->t('Name'),
      $this->t('Description'),
      $this->t('Operations'),
    ];
    $rows = [];

    if (!empty($remote_sources)) {
      foreach ($remote_sources as $remote_source) {
        $rows[] = [
          $remote_source['label'],
          $remote_source['description'],
          Link::createFromRoute($this->t('Configure'), 'ghi_content.remote.settings', [
            'remote_source' => $remote_source['id'],
          ]),
        ];
      }
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No remote sources are currently available'),
    ];
  }

}
