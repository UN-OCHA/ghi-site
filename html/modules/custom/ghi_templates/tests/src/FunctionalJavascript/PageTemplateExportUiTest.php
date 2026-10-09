<?php

namespace Drupal\Tests\ghi_templates\FunctionalJavascript;

/**
 * Tests page layout configuration import and export.
 *
 * @group ghi_templates
 */
class PageTemplateExportUiTest extends PageTemplateUiTestBase {

  /**
   * Tests the Export UI.
   */
  public function testExportUi() {
    $node = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor([
      'import page layout configuration code',
      'show page layout configuration code',
    ]);

    // Go to node view page.
    $this->drupalGet($node->toUrl()->toString());

    // The "Import" should be available.
    $this->assertTemplateLink('import');
    // The "Export" should not be available because this is an empty
    // layout using the DefaultSectionStorage.
    $this->assertNoTemplateLink('export');

    // Add a block.
    $this->addInlineBlock();
    $this->drupalGet($node->toUrl()->toString());

    // The "Import" should be available.
    $this->assertTemplateLink('import');
    // The "Export" should now be available.
    $this->assertTemplateLink('export');

    $this->loginEditor([
      'import page layout configuration code',
    ]);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertTemplateLink('import');
    $this->assertNoTemplateLink('export');

    $this->loginEditor([
      'show page layout configuration code',
    ]);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertNoTemplateLink('import');
    $this->assertTemplateLink('export');
  }

  /**
   * Tests the Export workflow.
   */
  public function testExportWorkflow() {
    $node_source = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor([
      'import page layout configuration code',
      'show page layout configuration code',
    ]);

    $this->drupalGet($node_source->toUrl()->toString());
    $this->addInlineBlock();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node view page and create a template from it.
    $this->drupalGet($node_source->toUrl()->toString());

    $this->clickTemplateLink('export');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Export page configuration for ' . $node_source->label());
    $export_config = $page->findField('Configuration export')->getValue();
    $this->assertNotEmpty($export_config);

    $node_target = $this->createNode([
      'type' => 'page',
    ]);
    $this->drupalGet($node_target->toUrl()->toString());
    $this->clickTemplateLink('import');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Import page configuration to ' . $node_target->label());
    $page->findField('Import from code')->setValue($export_config);

    $this->clickButtonWithText('Validate');
    $assert_session->assertWaitOnAjaxRequest();
    $this->htmlOutput(NULL);

    $assert_session->elementTextContains('css', 'form.layout-builder-import-page-config', 'Select the elements that you want to import.');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-table"]');
    $this->clickButtonWithText('Import');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('You have unsaved changes.');
    $this->htmlOutput(NULL);
  }

}
