<?php

namespace Drupal\Tests\ghi_templates\FunctionalJavascript;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_blocks\FunctionalJavascript\BlockUiBase;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the GHI templates.
 */
#[Group('ghi_templates')]
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
      'field_year' => 'Year',
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
   * Tests source policy in the actual picker and direct add routes.
   */
  public function testTemplateSourceRestrictions(): void {
    $source = $this->createNode(['type' => 'page']);
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $source->save();
    $template = PageTemplate::create([
      'title' => 'Source-scoped template',
      'field_entity_reference' => $source,
    ]);
    $template->save();
    $this->loginEditor(['administer page templates', 'configure any layout']);
    $parameters = [
      'section_storage_type' => 'overrides',
      'section_storage' => 'page_template.' . $template->id(),
      'delta' => 0,
      'region' => 'content',
    ];
    $picker_url = Url::fromRoute('layout_builder.choose_block', $parameters);
    $add_url = Url::fromRoute('layout_builder.add_block', $parameters + ['plugin_id' => 'system_powered_by_block']);

    $this->drupalGet($picker_url);
    $this->assertSession()->linkExists('Powered by Drupal');

    // Policy changes affect additions, not the template's existing components.
    $display = $this->container->get('entity_display.repository')->getViewDisplay('node', 'page');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'restricted_categories' => ['System'],
    ])->save();
    $this->drupalGet($picker_url);
    $this->assertSession()->linkNotExists('Powered by Drupal');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
    $template = PageTemplate::load($template->id());
    $this->assertCount(1, $template->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponents());

    $source->delete();
    $this->drupalGet($template->toUrl());
    $this->assertSession()->pageTextContains('The source page no longer exists.');
    $this->assertSession()->pageTextContains('Existing elements are retained');
    $this->drupalGet($picker_url);
    $this->assertSession()->linkNotExists('Powered by Drupal');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
  }

  /**
   * Tests cached block imports recheck changed or deleted template origins.
   */
  public function testCachedBlockImportPlacement(): void {
    $this->loginEditor(['administer page templates', 'configure any layout']);
    $configuration = $this->container->get('plugin.manager.block')->createInstance('system_powered_by_block')->getConfiguration();
    $export = Yaml::encode([
      'element' => $configuration['id'],
      'conf' => $configuration,
      'hash' => md5($configuration['id'] . '|' . Yaml::encode($configuration)),
    ]);

    foreach (['restricted origin', 'deleted origin'] as $change) {
      $display = $this->container->get('entity_display.repository')->getViewDisplay('node', 'page');
      $display->unsetThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction')->save();
      $source = $this->createNode(['type' => 'page']);
      $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', $configuration);
      $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
      $source->save();
      $template = PageTemplate::create(['title' => $change, 'field_entity_reference' => $source]);
      $template->save();
      $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($template)]);
      $before = array_map(static fn(Section $section) => $section->toArray(), $storage->getSections());
      $this->assertFalse($this->container->get('layout_builder.tempstore_repository')->has($storage));
      $this->drupalGet(Url::fromRoute('ghi_blocks.import_block', [
        'section_storage_type' => 'overrides',
        'section_storage' => $storage->getStorageId(),
        'delta' => 0,
        'region' => 'content',
      ]));
      $this->getSession()->getPage()->findField('config')->setValue($export);
      $this->clickButtonWithText('Validate and import');
      $this->assertNotNull($this->assertSession()->waitForElement('css', 'input[data-drupal-selector="edit-actions-submit"]'));
      $this->assertSession()->buttonExists('Add block');
      $this->getSession()->getPage()->findField('Title')->setValue('Pending imported element');
      $build_id = $this->assertSession()->elementExists('css', 'input[name="form_build_id"]')->getValue();

      // Keep the already rendered second step: the next POST restores its
      // cached form and serialized template rather than rebuilding the wizard.
      if ($change === 'restricted origin') {
        $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
          'restricted_categories' => ['System'],
        ])->save();
      }
      else {
        $source->delete();
      }
      $document_origin = $this->getSession()->evaluateScript('performance.timeOrigin');
      $this->clickButtonWithText('Add block');
      $this->assertNotNull($this->assertSession()->waitForElementVisible('css', 'form.layout-builder-import-block [data-drupal-messages]'));
      $this->assertSession()->elementTextContains('css', 'form.layout-builder-import-block [data-drupal-messages]', 'This element is not allowed in this layout.');
      $this->assertNotSame($document_origin, $this->getSession()->evaluateScript('performance.timeOrigin'));
      $this->assertSame($build_id, $this->assertSession()->elementExists('css', 'input[name="form_build_id"]')->getValue());
      $this->assertSession()->fieldValueEquals('settings[label]', 'Pending imported element');
      $this->assertFalse($this->container->get('layout_builder.tempstore_repository')->has($storage));
      $this->container->get('entity_type.manager')->getStorage('page_template')->resetCache([$template->id()]);
      $after = PageTemplate::load($template->id())->get(OverridesSectionStorage::FIELD_NAME)->getSections();
      $this->assertSame($before, array_map(static fn(Section $section) => $section->toArray(), $after));
    }
  }

  /**
   * Tests Back on both real wizards when all importable rows are restricted.
   */
  public function testBackFromFullyRestrictedConfirmation(): void {
    $source = $this->createNode(['type' => 'page']);
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $source->save();
    foreach (['First template', 'Selected template'] as $title) {
      $template = PageTemplate::create(['title' => $title, 'field_entity_reference' => $source]);
      $template->save();
    }
    $export = Yaml::encode($this->container->get('ghi_templates.manager')->exportSectionStorage($source));
    $destination = $this->createNode(['type' => 'page']);
    $before = $destination->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $destination_storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($destination)]);
    $display = $this->container->get('entity_display.repository')->getViewDisplay('node', 'page');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'restricted_categories' => ['System'],
    ])->save();
    $this->loginEditor(['use page templates', 'import page layout configuration code', 'configure any layout']);
    $parameters = [
      'entity_type' => 'node',
      'entity' => $destination->id(),
      'section_storage_type' => 'defaults',
      'section_storage' => 'node.page.default',
    ];

    foreach (['apply', 'import'] as $workflow) {
      $route = $workflow === 'apply' ? 'ghi_templates.entity.page_template.apply' : 'ghi_templates.entity.page_config.import';
      $this->drupalGet(Url::fromRoute($route, $parameters));
      if ($workflow === 'apply') {
        $this->clickElement('input[name="page_template"][value="' . $template->id() . '"]');
      }
      else {
        $this->getSession()->getPage()->findField('config')->setValue($export);
      }
      $this->clickButtonWithText('Validate');
      $this->assertSession()->waitForElement('css', 'table[data-drupal-selector="edit-table"]');
      $this->assertSession()->pageTextContains('This element is not allowed in this layout.');
      $this->assertSession()->elementExists('css', 'table[data-drupal-selector="edit-table"] tbody input[type="checkbox"][disabled]');

      // Only Back skips the required selection. Actual Import remains blocked.
      $this->clickButtonWithText('Import');
      $this->assertSession()->elementExists('css', 'table[data-drupal-selector="edit-table"]');
      $this->clickButtonWithText('Back');
      $this->assertSession()->waitForElementRemoved('css', 'table[data-drupal-selector="edit-table"]');
      if ($workflow === 'apply') {
        $this->assertSession()->fieldValueEquals('page_template', $template->id());
      }
      else {
        $this->assertSession()->fieldValueEquals('config', $export);
      }
      $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$destination->id()]);
      $this->assertSame($before, $this->container->get('entity_type.manager')->getStorage('node')->load($destination->id())->get(OverridesSectionStorage::FIELD_NAME)->getValue());
      $this->assertFalse($this->container->get('layout_builder.tempstore_repository')->has($destination_storage));
    }
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

    // Open the source page and export its layout configuration.
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

  /**
   * Expand the dropbutton for the given action type.
   *
   * @param string $type
   *   The type of dropdown, e.g. 'template' or 'page_template'.
   */
  protected function expandDropButton($type) {
    $this->clickElement('.layout-builder-ipe-actions .dropbutton-wrapper.layout-builder-ipe--link-' . $type . ' .dropbutton-toggle button');
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
   * Click a frontend link for templates.
   *
   * @param string $type
   *   The action type.
   */
  protected function clickTemplateLink($type) {
    $this->assertTemplateLink($type);
    $this->clickElement('.layout-builder-ipe-actions li.dropbutton-action.' . $type . ' > a');
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
