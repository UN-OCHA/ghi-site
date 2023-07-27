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
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_subpages\SubpageManager;
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
    $allowed_bundles = array_merge([
      ArticleManager::ARTICLE_BUNDLE,
      DocumentManager::DOCUMENT_BUNDLE,
    ], SectionManager::SECTION_BUNDLES, SubpageManager::SUPPORTED_SUBPAGE_TYPES);
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
    $breadcrumb->addLink(Link::createFromRoute($this->t('Administration'), 'system.admin'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Content'), 'system.admin_content'));

    $section_list_route = SectionManager::SECTION_LIST_ROUTE;
    $current_item_title = NULL;
    switch ($node->bundle()) {
      case ArticleManager::ARTICLE_BUNDLE:
        $breadcrumb->addLink(Link::createFromRoute($this->t('Article pages'), ArticleListController::ARTICLE_LIST_ROUTE));
        break;

      case DocumentManager::DOCUMENT_BUNDLE:
        $breadcrumb->addLink(Link::createFromRoute($this->t('Documents'), DocumentListController::DOCUMENT_LIST_ROUTE));
        break;
    }

    if (in_array($node->bundle(), SectionManager::SECTION_BUNDLES)) {
      $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), $section_list_route));
      if ($node->hasField('field_base_object')) {
        $base_object_type = $node->get('field_base_object')->entity->bundle();
        $section_type = $this->entityTypeManager->getStorage('base_object_type')->load($base_object_type)->label();
        $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), $section_list_route, [], [
          'query' => [
            'type' => $base_object_type,
          ],
        ]));
      }
    }

    if (in_array($node->bundle(), SubpageManager::SUPPORTED_SUBPAGE_TYPES) && $section = $node->get('field_entity_reference')->entity) {
      $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), $section_list_route));
      if ($section->hasField('field_base_object')) {
        $base_object_type = $section->get('field_base_object')->entity->bundle();
        $section_type = $this->entityTypeManager->getStorage('base_object_type')->load($base_object_type)->label();
        $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), $section_list_route, [], [
          'query' => [
            'type' => $base_object_type,
          ],
        ]));
      }
      $breadcrumb->addLink($section->toLink());
    }

    $breadcrumb->addLink($node->toLink($current_item_title));
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
