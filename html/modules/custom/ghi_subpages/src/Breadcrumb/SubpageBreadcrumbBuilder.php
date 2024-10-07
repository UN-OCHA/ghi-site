<?php

namespace Drupal\ghi_subpages\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Breadcrumb builder for subpage backend pages.
 */
class SubpageBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouterInterface $router) {
    $this->entityTypeManager = $entity_type_manager;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    return ($node instanceof SubpageNodeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    /** @var \Drupal\ghi_subpages\Entity\SubpageNodeInterface $node */
    $node = $route_match->getParameter('node');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $parent = $node->getParentNode();
    while ($parent instanceof SubpageNodeInterface) {
      /** @var \Drupal\ghi_subpages\Entity\SubpageNodeInterface $parent */
      $parents[] = $parent;
      $parent = $parent->getParentNode();
    }
    if ($parent = $node->getParentBaseNode()) {
      $parents[] = $parent;
    }

    if (empty($parents)) {
      return $breadcrumb;
    }

    $section_list_route = SectionManager::SECTION_LIST_ROUTE;
    foreach (array_reverse($parents) as $parent) {
      if ($parent instanceof SectionNodeInterface && $this->router->getRouteCollection()->get($section_list_route) !== NULL) {
        $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), $section_list_route));
        $base_object = $parent->getBaseObject();
        if ($base_object) {
          $section_type = $base_object->type->entity->label();
          $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), $section_list_route, [], [
            'query' => [
              'type' => $base_object->bundle(),
            ],
          ]));
        }
      }
      $breadcrumb->addLink($parent->toLink());
    }

    $breadcrumb->addLink($node->toLink());
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
