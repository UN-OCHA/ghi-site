<?php

namespace Drupal\ghi_teams\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Breadcrumb builder for team backend pages.
 */
class TeamsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $term = $route_match->getParameter('taxonomy_term');
    if ($term instanceof Term && $term->bundle() == 'team') {
      return TRUE;
    }
    $vocabulary = $route_match->getParameter('taxonomy_vocabulary');
    if ($vocabulary instanceof Vocabulary && $vocabulary->id() == 'team') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('People'), 'entity.user.collection'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Teams'), 'view.teams.page_teams'));
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
