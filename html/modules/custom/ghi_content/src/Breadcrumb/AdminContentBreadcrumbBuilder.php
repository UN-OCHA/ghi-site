<?php

namespace Drupal\ghi_content\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\node\NodeInterface;

/**
 * Breadcrumb builder for content backend pages.
 */
class AdminContentBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    $allowed_bundles = [ArticleManager::ARTICLE_BUNDLE, 'document', 'section', 'global_section'];
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
        $breadcrumb->addLink(Link::createFromRoute($this->t('Articles'), 'view.content.page_articles'));
        break;

      case 'document':
        $breadcrumb->addLink(Link::createFromRoute($this->t('Documents'), 'view.content.page_documents'));
        break;

      case 'section':
      case 'global_section':
        $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), 'view.content.page_sections'));

        if ($node->hasField('field_base_object')) {
          $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($node->bundle())->label();
          $base_object_type = $node->field_base_object->entity->bundle();
          $section_type = \Drupal::entityTypeManager()->getStorage('base_object_type')->load($base_object_type)->label();
          $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), 'view.content.page_sections', [], [
            'query' => [
              'type' => $base_object_type,
            ],
          ]));
        }
        break;
    }

    $breadcrumb->addLink($node->toLink($current_item_title));
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
