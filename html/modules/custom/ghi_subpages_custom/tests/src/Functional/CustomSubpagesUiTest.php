<?php

namespace Drupal\Tests\ghi_subpages_custom\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ghi_subpages_custom\Traits\CustomSubpageTestTrait;

/**
 * Tests aspects of the custom subpages UI.
 *
 * @group ghi_subpages_custom
 */
class CustomSubpagesUiTest extends BrowserTestBase {

  use CustomSubpageTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_subpages_custom',
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

    $this->createCustomSubpageContentTypes();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'access content overview',
      'administer nodes',
      'bypass node access',
    ]));
  }

  /**
   * Tests the custom subpages listing.
   */
  public function testSubpagesListing() {
    $section = $this->createSection();
    $custom_subpage = $this->createCustomSubpage($section);
    $custom_subpage->setTitle('Custom overview');
    $custom_subpage->setPublished();
    $custom_subpage->save();

    $this->drupalGet('/node/' . $section->id() . '/pages');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Subpages for Section ' . $section->label());
    $assert_session->pageTextContains('Custom subpages');
    $assert_session->elementExists('css', 'a[href="/node/add/custom_subpage?section=' . $section->id() . '"]');
    $assert_session->elementsCount('css', '#edit-subpages-custom-subpage tbody tr', 1);
    $assert_session->elementTextContains('css', '#edit-subpages-custom-subpage tbody tr', 'Custom overview');
    $assert_session->elementExists('css', '#edit-subpages-custom-subpage a[href="/node/' . $custom_subpage->id() . '"]');
    $assert_session->elementTextEquals('css', '#edit-subpages-custom-subpage a[href="/node/' . $custom_subpage->id() . '"]', 'Custom overview');
  }

}
