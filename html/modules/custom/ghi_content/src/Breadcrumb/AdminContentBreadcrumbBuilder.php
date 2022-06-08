<?php

namespace Drupal\ghi_content\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
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
      'document',
    ], SectionManager::SECTION_BUNDLES, SubpageHelper::SUPPORTED_SUBPAGE_TYPES);
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

    $current_item_title = NULL;
    switch ($node->bundle()) {
      case ArticleManager::ARTICLE_BUNDLE:
        $breadcrumb->addLink(Link::createFromRoute($this->t('Article pages'), 'view.content.page_articles'));
        break;

      case 'document':
        $breadcrumb->addLink(Link::createFromRoute($this->t('Documents'), 'view.content.page_documents'));
        break;
    }

    if (in_array($node->bundle(), SectionManager::SECTION_BUNDLES)) {
      $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), 'view.content.page_sections'));
      if ($node->hasField('field_base_object')) {
        $base_object_type = $node->get('field_base_object')->entity->bundle();
        $section_type = $this->entityTypeManager->getStorage('base_object_type')->load($base_object_type)->label();
        $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), 'view.content.page_sections', [], [
          'query' => [
            'type' => $base_object_type,
          ],
        ]));
      }
    }

    if (in_array($node->bundle(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
      $section = $node->get('field_entity_reference')->entity;
      $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), 'view.content.page_sections'));
      if ($section->hasField('field_base_object')) {
        $base_object_type = $section->get('field_base_object')->entity->bundle();
        $section_type = $this->entityTypeManager->getStorage('base_object_type')->load($base_object_type)->label();
        $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), 'view.content.page_sections', [], [
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
