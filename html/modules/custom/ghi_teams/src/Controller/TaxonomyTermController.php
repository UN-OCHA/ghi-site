<?php

namespace Drupal\ghi_teams\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_teams\Entity\Team;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
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
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->adminContext = $container->get('router.admin_context');
    return $instance;
  }

  /**
   * Route access callback.
   *
   * This checks view access for taxonomy terms that are marked as admin routes
   * and specifically also for team pages.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(TermInterface $taxonomy_term, AccountInterface $account) {
    if ($this->adminContext->isAdminRoute() && !$account->isAuthenticated()) {
      return AccessResult::forbidden();
    }
    if ($taxonomy_term instanceof Team) {
      return AccessResult::allowedIf($account->hasPermission('administer teams'));
    }
    return $taxonomy_term->access('view', NULL, TRUE);
  }

  /**
   * Title callback for the term add page.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The taxonomy term.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function addTitle(VocabularyInterface $taxonomy_vocabulary) {
    return $this->t('Add @label', [
      '@label' => strtolower($taxonomy_vocabulary->label()),
    ]);
  }

  /**
   * Title callback for the term edit page.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function editTitle(TermInterface $taxonomy_term) {
    return $this->t('Edit @label', [
      '@label' => strtolower($taxonomy_term->vid->entity->label()),
    ]);
  }

}
