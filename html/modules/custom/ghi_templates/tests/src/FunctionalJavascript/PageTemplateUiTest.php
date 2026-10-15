<?php

namespace Drupal\Tests\ghi_templates\FunctionalJavascript;

/**
 * Tests the page template UI and workflows.
 *
 * @group ghi_templates
 */
class PageTemplateUiTest extends PageTemplateUiTestBase {

  /**
   * Tests the Page template UI.
   */
  public function testPageTemplateUi() {
    $node = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor([
      'use page templates',
      'create page templates',
    ]);

    // Go to node view page.
    $this->drupalGet($node->toUrl()->toString());

    // The page templates should be available, even if no templates exist yet.
    $this->assertTemplateLink('apply');
    // The "Save as template" should not be available because this is an empty
    // layout using the DefaultSectionStorage.
    $this->assertNoTemplateLink('store');

    // Add a block.
    $this->addInlineBlock();
    $this->drupalGet($node->toUrl()->toString());

    // The page templates should be available, even if no templates exist yet.
    $this->assertTemplateLink('apply');
    // The "Save as template" should now be available.
    $this->assertTemplateLink('store');

    $this->loginEditor([
      'use page templates',
    ]);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertTemplateLink('apply');
    $this->assertNoTemplateLink('store');

    $this->loginEditor([
      'create page templates',
    ]);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertNoTemplateLink('apply');
    $this->assertTemplateLink('store');
  }

  /**
   * Tests the Page template workflow.
   */
  public function testPageTemplateWorkflow() {
    $node_source = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor([
      'use page templates',
      'create page templates',
    ]);

    $this->drupalGet($node_source->toUrl()->toString());
    $this->addInlineBlock();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node view page and create a template from it.
    $this->drupalGet($node_source->toUrl()->toString());
    $this->expandDropButton('page_template');
    $this->htmlOutput(NULL);

    $this->clickTemplateLink('store');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $this->clickButtonWithText('Create new template');

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('The page template Page template has been saved.');
    $this->htmlOutput(NULL);

    $node_target = $this->createNode([
      'type' => 'page',
    ]);
    $this->drupalGet($node_target->toUrl()->toString());
    $this->clickTemplateLink('apply');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Apply a page template to ' . $node_target->label());
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="0"]');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="1"]');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="2"]');
    $assert_session->elementTextContains('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="1"]', 'Page template');
    $assert_session->elementTextContains('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="2"]', 'Page: ' . $node_source->label());
    $this->htmlOutput(NULL);

    $this->clickButtonWithText('Validate');
    $assert_session->assertWaitOnAjaxRequest();
    $this->htmlOutput(NULL);

    $assert_session->elementTextContains('css', 'form.layout-builder-apply-page-template', 'Select the elements that you want to import.');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-table"]');
    $this->clickButtonWithText('Import');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('You have unsaved changes.');
    $this->htmlOutput(NULL);
  }

  /**
   * Tests that unique labels are enforced for page templates.
   */
  public function testPageTemplateUniqueLabelValidation() {
    $node_source = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor([
      'use page templates',
      'create page templates',
    ]);

    $this->drupalGet($node_source->toUrl()->toString());
    $this->addInlineBlock();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node view page and create a template from it.
    $this->drupalGet($node_source->toUrl()->toString());
    $this->expandDropButton('page_template');
    $this->htmlOutput(NULL);

    $this->clickTemplateLink('store');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $this->clickButtonWithText('Create new template');

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('The page template Page template has been saved.');
    $this->htmlOutput(NULL);

    // Now try that again with the same label and confirm that we see a
    // validation error.
    $this->drupalGet($node_source->toUrl()->toString());
    $this->expandDropButton('page_template');
    $this->htmlOutput(NULL);

    $this->clickTemplateLink('store');
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $this->clickButtonWithText('Create new template');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '.form-item-name', 'Page template is already in use. Please choose a different value.');
    $this->htmlOutput(NULL);
  }

}
