<?php

namespace Drupal\Tests\ghi_subpages_custom\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ghi_subpages_custom\Traits\CustomSubpageTestTrait;

/**
 * Tests aspects of the subpages UI.
 *
 * @group ghi_custom_subpages
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
   * Tests the subpages listing.
   */
  public function testSubpagesListing() {
    // Create a section, which should also create the subpages.
    $section = $this->createSection();
    $this->drupalGet('/node/' . $section->id() . '/pages');
  }

}
