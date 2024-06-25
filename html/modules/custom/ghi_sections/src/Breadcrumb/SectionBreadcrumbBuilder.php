<?php

namespace Drupal\ghi_sections\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionManager;
use Symfony\Component\Routing\RouterInterface;

/**
 * Breadcrumb builder for section backend pages.
 */
class SectionBreadcrumbBuilder implements BreadcrumbBuilderInterface {

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
    return ($node instanceof SectionNodeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface $node */
    $node = $route_match->getParameter('node');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $section_list_route = SectionManager::SECTION_LIST_ROUTE;

    if ($this->router->getRouteCollection()->get($section_list_route) !== NULL) {
      $breadcrumb->addLink(Link::createFromRoute($this->t('Sections'), $section_list_route));
      $base_object = $node->getBaseObject();
      if ($base_object) {
        $section_type = $base_object->type->entity->label();
        $breadcrumb->addLink(Link::createFromRoute($this->t('@type Sections', ['@type' => $section_type]), $section_list_route, [], [
          'query' => [
            'type' => $base_object->bundle(),
          ],
        ]));
      }
    }

    $breadcrumb->addLink($node->toLink());
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
