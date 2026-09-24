<?php

namespace Drupal\Tests\ghi_templates\FunctionalJavascript;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_blocks\FunctionalJavascript\BlockUiBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Provides setup and helpers for page template browser tests.
 *
 * @group ghi_templates
 */
abstract class PageTemplateUiTestBase extends BlockUiBase {

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
