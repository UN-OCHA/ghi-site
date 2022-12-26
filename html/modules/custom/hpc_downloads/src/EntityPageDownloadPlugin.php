<?php

namespace Drupal\hpc_downloads;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_downloads\DownloadSource\EntityPageSource;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Interface declaration for generic HPC downloads.
 */
class EntityPageDownloadPlugin implements HPCDownloadPluginInterface, HPCDownloadPDFInterface {

  use StringTranslationTrait;

  /**
   * The entity object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Public constructor.
   */
  public function __construct(EntityInterface $entity, RequestStack $request_stack) {
    $this->entity = $entity;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(EntityInterface $entity, ContainerInterface $container) {
    return new static(
      $entity,
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
  public function getPluginType() {
    return $this->entity->getEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->entity->uuid();
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
    return $this->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadCaption() {
    return $this->entity->label();
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
    return new EntityPageSource($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->entity->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->entity->getCacheTags();
  }

}
