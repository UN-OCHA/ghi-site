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
    foreach (self::SUBPAGE_BUNDLES as $bundle) {
      $this->assertSession()->pageTextNotContains(ucfirst($bundle));
    }
    foreach (self::SUBPAGE_BUNDLES as $bundle) {
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
    $assert_session->elementsCount('css', '#edit-subpages-standard tbody tr', count(self::SUBPAGE_BUNDLES));
    // Confirm the first columns are checkboxes and that their tds have the
    // right class.
    $assert_session->elementsCount('css', '#edit-subpages-standard tbody tr td:first-child.subpages-bulk-form input[type="checkbox"]', count(self::SUBPAGE_BUNDLES));
  }

}
