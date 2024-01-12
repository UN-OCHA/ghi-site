<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the GHI specific block UI.
 *
 * @group ghi_sections
 */
class BlockUiTest extends WebDriverTestBase {

  use BaseObjectTestTrait;
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;

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
    'gin_lb',
    'gin_toolbar',
    'ghi_blocks',
    'ghi_gin',
  ];

  /**
   * Declare modules that the used theme depends on.
   *
   * @var array
   */
  protected static $themeDependencies = [
    'gin_lb',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'common_design_subtheme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertTrue(\Drupal::service('theme_installer')->install(['gin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'common_design_subtheme')
      ->set('admin', 'gin')
      ->save();

    $this->container->get('config.factory')
      ->getEditable('gin.settings')
      ->merge([
        'preset_accent_color' => 'custom',
        'preset_focus_color' => 'gin',
        'enable_darkmode' => '0',
        'classic_toolbar' => 'horizontal',
        'secondary_toolbar_frontend' => FALSE,
        'high_contrast_mode' => FALSE,
        'accent_color' => '#4D4D4D',
        'focus_color' => '',
        'layout_density' => 'default',
        'show_description_toggle' => FALSE,
        'show_user_theme_settings' => FALSE,
      ])
      ->save();

    // Create a user with sufficient permissions to setup Layout Builder.
    $this->drupalLogin($this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      'configure any layout',
      'create and edit custom blocks',
      'administer node display',
      'administer node fields',
      'access contextual links',
      'administer layout builder ipe',
      'view the administration theme',
    ]));

    // Create content types.
    $this->createContentType(['type' => 'page']);

    // Create a block content type.
    $this->createBlockContentType('basic', 'Basic block');

    // Enable layout builder for the first bundle.
    $this->drupalGet('admin/structure/types/manage/page/display/default');

    $page = $this->getSession()->getPage();
    $page->find('css', '[name="layout[enabled]"]')->check();
    $page->find('css', '[name="layout[allow_custom]"]')->check();
    $page->find('css', '[name="layout[layout_builder_ipe]"]')->check();
    $page->find('css', '[value="Save"]')->click();
    $this->assertSession()->pageTextContains('Your settings have been saved.');
    $this->drupalGet('admin/structure/types/manage/page/display/default');

    $this->drupalLogout();
  }

  /**
   * {@inheritdoc}
   */
  protected function installDefaultThemeFromClassProperty(ContainerInterface $container) {
    // We install the theme dependencies first, otherwhise our custom theme
    // refuses to install.
    $modules = static::$themeDependencies;
    $success = $container->get('module_installer')->install($modules, TRUE);
    $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => implode(', ', $modules)]));

    // And we need to clear the config cache so that the theme installer is
    // aware of the newly installed modules.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    $config_factory->clearStaticCache();

    parent::installDefaultThemeFromClassProperty($container);
  }

  /**
   * Tests the block configuration page.
   */
  public function testBlockConfiguration() {
    $node = $this->createNode([
      'type' => 'page',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'access content',
      'access content overview',
      'access contextual links',
      'access toolbar',
      'administer nodes',
      'create and edit custom blocks',
      'configure editable page node layout overrides',
      'edit any page content',
      'use layout builder ipe on editable page node layout overrides',
      'use inline blocks',
      'view the administration theme',
    ]));

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node view page.
    $this->drupalGet($node->toUrl()->toString());

    // Click the customize link.
    $assert_session->elementExists('css', '#layout-builder-ipe-wrapper');
    $assert_session->elementExists('css', '.layout-builder-ipe-actions');
    $assert_session->linkExists('Customize');
    $page->clickLink('Customize');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '#layout-builder-ipe-wrapper.edit-layout');
    $assert_session->elementExists('css', '#layout-builder-ipe-wrapper.edit-layout');

    // Check that the form and the two main buttons are there.
    $assert_session->elementExists('css', 'form.layout-builder-form');
    $assert_session->buttonExists('Discard changes');
    $assert_session->buttonExists('Save layout');

    // Open the "add new block" dialog.
    $assert_session->linkExists('Add block');
    $page->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();

    // Confirm that block selection opens in a modal.
    $assert_session->elementTextContains('css', '#layout-builder-modal', 'Choose a block type from the following categories');
    $assert_session->linkExists('Admin restricted');
    $page->clickLink('Admin restricted');
    $assert_session->linkExists('Generic HTML');
    $page->clickLink('Generic HTML');
    $assert_session->assertWaitOnAjaxRequest();

    // Confirm modal and UI details.
    $assert_session->elementExists('css', '#layout-builder-modal');
    $assert_session->elementExists('css', '.glb-canvas-form__actions a.dialog-cancel.use-ajax');
    $assert_session->elementExists('css', '.glb-canvas-form__actions input[data-drupal-selector="edit-actions-submit"]');

    // Submit the block data.
    $page->findField('Title')->setValue('Hello World');
    $page->findField('Display title')->setValue(1);
    $page->findField('Body')->setValue('Body says hello');
    $button = $assert_session->elementExists('css', '#layout-builder-add-block .glb-button--primary');
    $button->press();

    // Confirm that it closes the modal and that the submitted data displays.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->elementNotExists('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.block-inline-blockbasic h2.cd-block-title', 'Hello World');
    $assert_session->elementContains('css', '.block-inline-blockbasic .field--name-body', 'Body says hello');
    $assert_session->pageTextContains('You have unsaved changes.');
    $this->htmlOutput(NULL);

    // Confirm contextual block links.
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-remove');
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-hide');
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-unhide');
    $configure_link = $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-update');

    // Confirm opening configuration and closing without changes works.
    $configure_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementExists('css', '#layout-builder-modal');
    $cancel_link = $assert_session->elementExists('css', '#layout-builder-modal a.dialog-cancel');
    $this->htmlOutput(NULL);

    $cancel_link->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $this->htmlOutput(NULL);
  }

  /**
   * Creates a block content type.
   *
   * @param string $id
   *   The block type id.
   * @param string $label
   *   The block type label.
   */
  protected function createBlockContentType($id, $label) {
    $bundle = BlockContentType::create([
      'id' => $id,
      'label' => $label,
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

}
