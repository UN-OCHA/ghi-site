<?php

namespace Drupal\ghi_teams\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for taxonomy terms.
 *
 * The main thing this does, is to change the page titles for the different
 * taxonomy pages (add, edit, delete, ...).
 *
 * @see \Drupal\ghi_teams\EventSubscriber\TaxonomyTermRouteSubscriber
 */
class TaxonomyTermController extends ControllerBase {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Public constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static($container->get('current_route_match'));
    return $instance;
  }

  /**
   * Title callback for the term add page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function addTitle() {
    /** @var \Drupal\taxonomy\VocabularyInterface $term */
    $vocabulary = $this->routeMatch->getParameter('taxonomy_vocabulary');
    return $vocabulary ? $this->t('Add @label', [
      '@label' => strtolower($vocabulary->label()),
    ]) : $this->t('Add term');
  }

  /**
   * Title callback for the term edit page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function editTitle() {
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $this->routeMatch->getParameter('taxonomy_term');
    return $term ? $this->t('Edit @label', [
      '@label' => strtolower($term->vid->entity->label()),
    ]) : $this->t('Edit term');
  }

}
