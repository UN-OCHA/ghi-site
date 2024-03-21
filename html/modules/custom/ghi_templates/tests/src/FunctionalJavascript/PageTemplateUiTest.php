<?php

namespace Drupal\Tests\ghi_templates\FunctionalJavascript;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_blocks\FunctionalJavascript\BlockUiBase;

/**
 * Tests the GHI templates.
 *
 * @group ghi_templates
 */
class PageTemplateUiTest extends BlockUiBase {

  use BaseObjectTestTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'inline_form_errors',
    'ghi_templates',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content types.
    $this->createBaseObjectType([
      'id' => 'plan',
      'label' => 'Plan',
      'hasYear' => TRUE,
    ]);
    $this->createLayoutBuilderContentType('section');
    $handler_settings = [
      'target_bundles' => ['plan'],
    ];
    $this->createEntityReferenceField('node', 'section', 'field_base_object', 'Base object', 'base_object', 'default', $handler_settings);

    // Create fields for the page template entity.
    $this->createEntityReferenceField('page_template', 'page_template', 'field_base_object', 'Base object', 'base_object', 'default', $handler_settings);
    $this->createEntityReferenceField('page_template', 'page_template', 'field_entity_reference', 'Source page', 'node', 'default', [
      'target_bundles' => ['page', 'section'],
    ]);

    // Enable layout builder for the page template entity.
    $page_template_display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'page_template',
      'bundle' => 'page_template',
      'status' => TRUE,
      'mode' => 'default',
    ]);
    $page_template_display->enableLayoutBuilder()
      ->setOverridable()
      ->setThirdPartySetting('layout_builder_ipe', 'enabled', TRUE)
      ->save();
  }

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

    $store_link = $this->assertTemplateLink('store');
    $store_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $button = $assert_session->buttonExists('Create new template');
    $button->press();

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('The page template Page template has been saved.');
    $this->htmlOutput(NULL);

    $node_target = $this->createNode([
      'type' => 'page',
    ]);
    $this->drupalGet($node_target->toUrl()->toString());
    $apply_link = $this->assertTemplateLink('apply');
    $apply_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Apply a page template to ' . $node_target->label());
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="0"]');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="1"]');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="2"]');
    $assert_session->elementTextContains('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="1"]', 'Page template');
    $assert_session->elementTextContains('css', 'table[data-drupal-selector="edit-page-template"] tbody tr td[data-column="2"]', 'Page: ' . $node_source->label());
    $this->htmlOutput(NULL);

    $button = $assert_session->buttonExists('Validate');
    $button->press();
    $assert_session->assertWaitOnAjaxRequest();
    $this->htmlOutput(NULL);

    $assert_session->elementTextContains('css', 'form.layout-builder-apply-page-template', 'Select the elements that you want to import.');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-table"]');
    $button = $assert_session->buttonExists('Import');
    $button->press();
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

    $store_link = $this->assertTemplateLink('store');
    $store_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $button = $assert_session->buttonExists('Create new template');
    $button->press();

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('The page template Page template has been saved.');
    $this->htmlOutput(NULL);

    // Now try that again with the same label and confirm that we see a
    // validation error.
    $this->drupalGet($node_source->toUrl()->toString());
    $this->expandDropButton('page_template');
    $this->htmlOutput(NULL);

    $store_link = $this->assertTemplateLink('store');
    $store_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Save as a new page template based on ' . $node_source->label());
    $page->findField('Label')->setValue('Page template');
    $this->htmlOutput(NULL);

    $button = $assert_session->buttonExists('Create new template');
    $button->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '.form-item-name', 'Page template is already in use. Please choose a different value.');
    $this->htmlOutput(NULL);
  }

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

    $export_link = $this->assertTemplateLink('export');
    $export_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Export page configuration for ' . $node_source->label());
    $export_config = $page->findField('Configuration export')->getValue();
    $this->assertNotEmpty($export_config);

    $node_target = $this->createNode([
      'type' => 'page',
    ]);
    $this->drupalGet($node_target->toUrl()->toString());
    $import_link = $this->assertTemplateLink('import');
    $import_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.ui-dialog-title', 'Import page configuration to ' . $node_target->label());
    $page->findField('Import from code')->setValue($export_config);

    $button = $assert_session->buttonExists('Validate');
    $button->press();
    $assert_session->assertWaitOnAjaxRequest();
    $this->htmlOutput(NULL);

    $assert_session->elementTextContains('css', 'form.layout-builder-import-page-config', 'Select the elements that you want to import.');
    $assert_session->elementExists('css', 'table[data-drupal-selector="edit-table"]');
    $button = $assert_session->buttonExists('Import');
    $button->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->pageTextContains('You have unsaved changes.');
    $this->htmlOutput(NULL);
  }

  /**
   * Expand the dropbutton for the given action type.
   *
   * @param string $type
   *   The type of dropdown, e.g. 'template' or 'page_template'.
   */
  protected function expandDropButton($type) {
    $page = $this->getSession()->getPage();
    $page->find('css', '.layout-builder-ipe-actions .dropbutton-wrapper.layout-builder-ipe--link-' . $type . ' .dropbutton-toggle button')->click();
  }

  /**
   * Assert a frontend link for templates.
   *
   * @param string $type
   *   The action type.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The link node.
   */
  protected function assertTemplateLink($type) {
    $title_map = [
      'apply' => 'Page templates',
      'store' => 'Save as template',
      'import' => 'Import',
      'export' => 'Export',
    ];
    $page = $this->getSession()->getPage();
    $link = $page->find('css', '.layout-builder-ipe-actions li.dropbutton-action.' . $type . ' > a');
    $this->assertEquals($title_map[$type], $link->getHtml());
    return $link;
  }

  /**
   * Assert a template link does not exists.
   *
   * @param string $type
   *   The action type.
   */
  protected function assertNoTemplateLink($type) {
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', '.layout-builder-ipe-actions');
    $assert_session->elementNotExists('css', '.layout-builder-ipe-actions li.dropbutton-action.' . $type);
  }

}
