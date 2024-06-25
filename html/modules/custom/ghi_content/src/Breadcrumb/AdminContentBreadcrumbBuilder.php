<?php

namespace Drupal\ghi_content\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Controller\ArticleListController;
use Drupal\ghi_content\Controller\DocumentListController;
use Drupal\node\NodeInterface;

/**
 * Breadcrumb builder for content backend pages.
 */
class AdminContentBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    $allowed_bundles = [
      ArticleManager::ARTICLE_BUNDLE,
      DocumentManager::DOCUMENT_BUNDLE,
    ];
    return ($node instanceof NodeInterface && in_array($node->bundle(), $allowed_bundles));
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $current_item_title = NULL;
    switch ($node->bundle()) {
      case ArticleManager::ARTICLE_BUNDLE:
        $breadcrumb->addLink(Link::createFromRoute($this->t('Article pages'), ArticleListController::ARTICLE_LIST_ROUTE));
        break;

      case DocumentManager::DOCUMENT_BUNDLE:
        $breadcrumb->addLink(Link::createFromRoute($this->t('Documents'), DocumentListController::DOCUMENT_LIST_ROUTE));
        break;
    }

    $breadcrumb->addLink($node->toLink($current_item_title));
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
