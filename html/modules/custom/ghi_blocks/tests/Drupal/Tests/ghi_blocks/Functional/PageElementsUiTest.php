<?php

namespace Drupal\Tests\ghi_blocks\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Tests aspects of the page elements UI.
 *
 * @group ghi_blocks
 */
class PageElementsUiTest extends BrowserTestBase {

  use SubpageTestTrait;
  use LayoutEntityHelperTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block_content',
    'toolbar',
    'admin_toolbar',
    'admin_toolbar_tools',
    'node',
    'field_ui',
    'ghi_blocks',
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

    LayoutBuilderEntityViewDisplay::load('node.' . self::SECTION_BUNDLE . '.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->setThirdPartySetting('layout_builder_ipe', 'enabled', TRUE)
      ->save();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'access content',
      'access content overview',
      'access contextual links',
      'access toolbar',
      'administer nodes',
      'create and edit custom blocks',
      'use inline blocks',
      'view the administration theme',
      'edit any ' . self::SECTION_BUNDLE . ' content',
      'configure editable ' . self::SECTION_BUNDLE . ' node layout overrides',
      'use layout builder ipe on editable ' . self::SECTION_BUNDLE . ' node layout overrides',
    ]));
  }

  /**
   * Tests the page element listing.
   */
  public function testPageElementListing() {
    // Create a section, which should also create the subpages.
    $section_node = $this->createSection();

    $section_storage = $this->getSectionStorageForEntity($section_node);
    // First, make sure we have an overridden section storage.
    if ($section_storage instanceof DefaultsSectionStorage) {
      $sections = $section_storage->getSections();
      $section_node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $section_node->save();
    }

    $this->drupalGet('/node/' . $section_node->id() . '/page-elements');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Page elements overview for Section ' . $section_node->label());
    $assert_session->pageTextContains('Element type');
    $assert_session->pageTextContains('Label');
    $assert_session->pageTextContains('Status');
    $assert_session->pageTextContains('Operations');

    // Confirm the first columns are checkboxes and that their tds have the
    // right class and that there are 2 of them (defaults: Body and Links).
    $assert_session->elementsCount('css', 'table#edit-elements tbody tr td:first-child.edit-content-bulk-form input[type="checkbox"]', 2);

    // Confirm the bulk operations dropdown and it's options.
    $assert_session->elementTextContains('css', 'select#edit-action option[value="unhide"]', 'Unhide');
    $assert_session->elementTextContains('css', 'select#edit-action option[value="hide"]', 'Hide');

    // Remove the first of the 2 elements.
    $remove_link = $assert_session->elementExists('css', 'table#edit-elements tbody tr:first-child td:last-child li.remove > a');
    $remove_link->click();

    $assert_session->pageTextContains('Are you sure you want to remove the page element?');
    $assert_session->pageTextContains('This will permanently remove the page element from this page. This cannot be undone.');
    $assert_session->elementExists('css', '#ghi-blocks-page-elements-action-confirm a.dialog-cancel');
    $confirm_link = $assert_session->elementExists('css', '#ghi-blocks-page-elements-action-confirm input[value="Remove the page element"]');
    $confirm_link->click();

    // $this->drupalGet('/node/' . $section_node->id() . '/page-elements');
    $assert_session->elementsCount('css', 'table#edit-elements tbody tr', 1);

  }

}
