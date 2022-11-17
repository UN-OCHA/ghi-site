<?php

namespace Drupal\hpc_downloads;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_downloads\DownloadSource\NodeSource;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Interface declaration for generic HPC downloads.
 */
class NodeDownloadPlugin implements HPCDownloadPluginInterface, HPCDownloadPDFInterface {

  use StringTranslationTrait;

  /**
   * The node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Public constructor.
   */
  public function __construct(NodeInterface $node, RequestStack $request_stack) {
    $this->node = $node;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(NodeInterface $node, ContainerInterface $container) {
    return new static(
      $node,
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUri() {
    $request = $this->requestStack->getCurrentRequest();
    // This might come from an IPE or form state context ($_POST).
    $current_path = $request->request->get('currentPath');
    // Or from a query argument, i.e. in download contexts.
    $uri = $request->query->get('uri') ?? $request->query->get('current_uri');
    if (!empty($current_path)) {
      $current_uri = $current_path;
    }
    elseif (!empty($uri)) {
      $current_uri = $uri;
    }
    else {
      $current_uri = $request->getRequestUri();
    }
    return '/' . ltrim($current_uri, '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->node->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->node->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableDownloadTypes() {
    $download_types = [
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF => $this->t('Download PDF'),
    ];
    return $download_types;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->node->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadCaption() {
    return $this->node->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadPdfTitle() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadPdfCaption() {
    return $this->getDownloadCaption();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadSource() {
    return new NodeSource($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->node->getCacheContexts();
  }

}
