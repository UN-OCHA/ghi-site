<?php

namespace Drupal\Tests\ghi_subpages\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Tests aspects of the subpages UI.
 *
 * @group ghi_subpages
 */
class SubpagesUiTest extends BrowserTestBase {

  use SubpageTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_subpages',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createSubpageContentTypes();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'access content overview',
      'administer nodes',
      'bypass node access',
    ]));
  }

  /**
   * Tests that subpages can't be created manually.
   */
  public function testPreventSubpageCreation() {
    $this->drupalGet('/node/add');
    $this->assertSession()->pageTextContains('Section');
    foreach ([...self::SUBPAGE_BUNDLES, 'needs', 'response'] as $bundle) {
      $this->assertSession()->pageTextNotContains(ucfirst($bundle));
    }
    foreach ([...self::SUBPAGE_BUNDLES, 'needs', 'response'] as $bundle) {
      $this->drupalGet('/node/add/' . $bundle);
      $this->assertSession()->pageTextContains('Access denied');
    }
  }

  /**
   * Tests that sections have a link to the subpages.
   */
  public function testSubpagesLink() {
    // Create a section, which should also create the subpages.
    $section = $this->createSection();
    $this->drupalGet('/admin/content');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains($section->label());
    $assert_session->elementExists('css', 'a[href="/node/' . $section->id() . '/pages"]');
    $assert_session->elementTextEquals('css', 'a[href="/node/' . $section->id() . '/pages"]', 'Subpages');

    $this->drupalGet($section->toUrl('edit-form'));
    $assert_session->pageTextContains('Edit Section ' . $section->label());
    $assert_session->elementExists('css', 'a[href="/node/' . $section->id() . '/pages"]');
    $assert_session->elementTextEquals('css', 'a[href="/node/' . $section->id() . '/pages"]', 'Subpages');
  }

  /**
   * Tests the subpages listing.
   */
  public function testSubpagesListing() {
    // Create a section, which should also create the subpages.
    $section = $this->createSection();
    $this->drupalGet('/node/' . $section->id() . '/pages');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Subpages for Section ' . $section->label());
    $assert_session->pageTextContains('Standard subpages');
    // Confirm as much rows as there are subpage types.
    $assert_session->elementsCount('css', '#edit-subpages-standard tbody tr', count(self::SUBPAGE_BUNDLES) + 2);
    // Confirm the first columns are checkboxes and that their tds have the
    // right class.
    $assert_session->elementsCount('css', '#edit-subpages-standard tbody tr td:first-child.ghi-subpages-admin-views-form-bulk-form input[type="checkbox"]', count(self::SUBPAGE_BUNDLES) + 2);
    $assert_session->elementNotExists('css', 'a[href="/node/' . $section->id() . '/pages/create/needs"]');
  }

  /**
   * Tests manual structural actions without changing local-task visibility.
   */
  public function testManualStandardSubpageActions() {
    $section = $this->createSection(['status' => 1]);
    $pages_url = '/node/' . $section->id() . '/pages';
    $this->drupalGet($pages_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'a[href="/node/' . $section->id() . '/pages/create/needs"]');
    $this->drupalGet('/node/' . $section->id() . '/pages/create/needs');
    $this->assertSession()->statusCodeEquals(403);

    $structure_user = $this->drupalCreateUser([
      'access administration pages',
      'bypass node access',
      'manage operation page structure',
      'publish any content',
      'unpublish any content',
    ]);
    $this->drupalLogin($structure_user);
    $this->drupalGet($pages_url);
    $this->assertSession()->elementExists('css', '#edit-subpages-standard .dropbutton-wrapper a[href="/node/' . $section->id() . '/pages/create/needs"][data-dialog-type="modal"].use-ajax');
    $this->drupalGet('/node/' . $section->id() . '/pages/create/needs', ['query' => ['destination' => '/admin/content']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Please confirm that the Needs subpage for ' . $section->label() . ' should be created');
    $this->submitForm([], 'Create page');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->assertSession()->pageTextContains('Created needs subpage.');
    $needs = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties([
      'type' => 'needs',
      'field_entity_reference' => $section->id(),
    ]);
    $this->assertCount(1, $needs);
    $child = reset($needs);
    $this->assertFalse($child->isPublished());
    $this->drupalGet($pages_url, ['query' => ['destination' => '/admin/content']]);
    $this->assertSession()->elementExists('css', '#edit-subpages-standard .dropbutton-wrapper a[href="/node/' . $section->id() . '/pages/delete/' . $child->id() . '"][data-dialog-type="modal"].use-ajax');
    $status_link = $this->assertSession()->elementExists('css', '#edit-subpages-standard a[href*="/node/' . $child->id() . '/toggleStatus"]');
    parse_str((string) parse_url($status_link->getAttribute('href'), PHP_URL_QUERY), $status_query);
    $this->assertSame($pages_url, $status_query['destination'] ?? NULL);
    $status_link->click();
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->drupalGet('/node/' . $section->id() . '/pages/delete/' . $child->id(), ['query' => ['destination' => '/admin/content']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete selected pages');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->assertSession()->pageTextContains('Deleted 1 standard subpages.');
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('node')->loadByProperties([
      'type' => 'needs',
      'field_entity_reference' => $section->id(),
    ]));

    $this->drupalGet($pages_url);
    $this->submitForm([
      'action' => 'create',
      'subpages_standard[bundle:needs]' => 'bundle:needs',
      'subpages_standard[bundle:response]' => 'bundle:response',
    ], 'Apply to selected items');
    $this->assertSession()->pageTextContains('Created needs subpage.');
    $this->assertSession()->pageTextContains('Created response subpage.');
    $children = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties([
      'field_entity_reference' => $section->id(),
    ]);
    $standard_ids = [];
    foreach ($children as $child) {
      if (in_array($child->bundle(), ['needs', 'response'], TRUE)) {
        $standard_ids[] = $child->id();
      }
    }
    $this->assertCount(2, $standard_ids);
    $editor_user = $this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($editor_user);
    $this->drupalGet('/node/' . $section->id() . '/pages/delete/' . $standard_ids[0]);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/node/' . $standard_ids[0] . '/delete');
    $this->assertSession()->statusCodeEquals(403);
    $account_switcher = $this->container->get('account_switcher');
    $account_switcher->switchTo($editor_user);
    $this->container->get('tempstore.private')->get('entity_delete_multiple_confirm')->set($editor_user->id() . ':node', [
      $standard_ids[0] => [$this->container->get('language_manager')->getDefaultLanguage()->getId()],
    ]);
    $account_switcher->switchBack();
    $this->drupalGet('/admin/content/node/delete');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($pages_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogin($structure_user);
    $this->drupalGet($pages_url);
    $this->submitForm([
      'action' => 'delete',
      'subpages_standard[node:' . $standard_ids[0] . ']' => 'node:' . $standard_ids[0],
      'subpages_standard[node:' . $standard_ids[1] . ']' => 'node:' . $standard_ids[1],
    ], 'Apply to selected items');
    $this->assertSession()->pageTextContains('Delete 2 standard subpages?');
    $this->submitForm([], 'Delete selected pages');
    $this->assertSession()->pageTextContains('Deleted 2 standard subpages.');
    foreach (['needs', 'response'] as $bundle) {
      $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('node')->loadByProperties([
        'type' => $bundle,
        'field_entity_reference' => $section->id(),
      ]));
    }
  }

}
