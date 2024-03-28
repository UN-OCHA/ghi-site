<?php

namespace Drupal\Tests\ghi_teams\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_teams\Controller\TaxonomyTermController;
use Drupal\ghi_teams\Entity\Team;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Tests the taxonomy term controller.
 */
class TaxonomyTermControllerTest extends UnitTestCase {

  /**
   * The taxonomy term controller.
   *
   * @var \Drupal\ghi_teams\Controller\TaxonomyTermController
   */
  private $taxonomyTermController;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ghi_teams',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_context = $this->prophesize(AdminContext::class);
    $admin_context->isAdminRoute()->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('router.admin_context', $admin_context->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->taxonomyTermController = TaxonomyTermController::create($container);
  }

  /**
   * Test access to the team admin page.
   */
  public function testAccessToTeamAdminPage() {
    $account_admin = $this->prophesize(AccountInterface::class);
    $account_admin->isAuthenticated()->willReturn(TRUE);
    $account_admin->hasPermission('administer teams')->willReturn(TRUE);

    $account_non_admin = $this->prophesize(AccountInterface::class);
    $account_non_admin->isAuthenticated()->willReturn(TRUE);
    $account_non_admin->hasPermission('administer teams')->willReturn(FALSE);

    $account_anonymous = $this->prophesize(AccountInterface::class);
    $account_anonymous->isAuthenticated()->willReturn(FALSE);
    $account_anonymous->hasPermission('administer teams')->willReturn(FALSE);

    $taxonomy_term = $this->prophesize(Team::class);
    $taxonomy_term->access('view', NULL, TRUE)->willReturn(AccessResult::allowed());

    // Team admins can access.
    $access = $this->taxonomyTermController->access($taxonomy_term->reveal(), $account_admin->reveal());
    $this->assertTrue($access->isAllowed());

    // Non team admins can't access.
    $access = $this->taxonomyTermController->access($taxonomy_term->reveal(), $account_non_admin->reveal());
    $this->assertFalse($access->isAllowed());

    // Anyonmous can't access.
    $access = $this->taxonomyTermController->access($taxonomy_term->reveal(), $account_anonymous->reveal());
    $this->assertFalse($access->isAllowed());
  }

  /**
   * Test access to other term pages.
   */
  public function testAccessToOtherTermPages() {
    $account = $this->prophesize(AccountInterface::class);
    $account->isAuthenticated()->willReturn(TRUE);

    $taxonomy_term = $this->prophesize(TermInterface::class);
    $taxonomy_term->access('view', NULL, TRUE)->willReturn(AccessResult::allowed());

    // This just tests that the taxonomy term controller class hands over the
    // access check to the taxonomy term.
    $access = $this->taxonomyTermController->access($taxonomy_term->reveal(), $account->reveal());
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Test the page titles on term form pages.
   */
  public function testEditPageTitles() {
    $vocabulary = $this->prophesize(VocabularyInterface::class);
    $vocabulary->label()->willReturn('Vocabulary');

    $reference = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference->addMethodProphecy((new MethodProphecy($reference, '__get', ['entity']))->willReturn($vocabulary->reveal()));

    $taxonomy_term = $this->prophesize(TermInterface::class);
    $taxonomy_term->vid = $reference->reveal();

    $title = $this->taxonomyTermController->addTitle($vocabulary->reveal());
    $this->assertEquals('Add vocabulary', (string) $title);

    $title = $this->taxonomyTermController->editTitle($taxonomy_term->reveal());
    $this->assertEquals('Edit vocabulary', (string) $title);
  }

}
