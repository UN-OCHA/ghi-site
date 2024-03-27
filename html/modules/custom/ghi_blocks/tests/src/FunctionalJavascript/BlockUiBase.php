<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for testing the GHI specific block UI.
 *
 * @group ghi_blocks
 */
abstract class BlockUiBase extends WebDriverTestBase {

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

    // Create a block content type.
    $this->createBlockContentType('basic', 'Basic block');

    // Create a layout builder enabled content type.
    $this->createLayoutBuilderContentType('page');
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
   * Login an editor user.
   *
   * @param array $permissions
   *   Optional array of additional permissions.
   */
  protected function loginEditor($permissions = []) {
    $this->drupalLogin($this->drupalCreateUser(array_merge([
      'access administration pages',
      'access content',
      'access content overview',
      'access contextual links',
      'access toolbar',
      'administer nodes',
      'create and edit custom blocks',
      'use inline blocks',
      'view the administration theme',
      'edit any page content',
      'configure editable page node layout overrides',
      'use layout builder ipe on editable page node layout overrides',
    ], $permissions)));
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

  /**
   * Create a node bundle with layout builder enabled.
   *
   * @param string $bundle
   *   The machine name of the bundle.
   */
  protected function createLayoutBuilderContentType($bundle) {
    // Create content types.
    $this->createContentType(['type' => $bundle]);

    LayoutBuilderEntityViewDisplay::load('node.' . $bundle . '.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->setThirdPartySetting('layout_builder_ipe', 'enabled', TRUE)
      ->save();
  }

  /**
   * Add an inline block to the current, layout builder-enabled, page.
   */
  protected function addInlineBlock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->clickLink('Customize');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Admin restricted');
    $page->clickLink('Generic HTML');
    $assert_session->assertWaitOnAjaxRequest();
    $page->findField('Title')->setValue('Hello World');
    $page->findField('Display title')->setValue(1);
    $page->findField('Body')->setValue('Body says hello');
    $button = $assert_session->elementExists('css', '#layout-builder-add-block .glb-button--primary');
    $button->press();
    $button = $assert_session->buttonExists('Save layout');
    $button->press();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
  }

}
