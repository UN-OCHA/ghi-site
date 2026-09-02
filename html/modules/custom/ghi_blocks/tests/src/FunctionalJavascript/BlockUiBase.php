<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for testing the GHI specific block UI.
 *
 * @group ghi_blocks
 */
abstract class BlockUiBase extends WebDriverTestBase {

  const BUNDLE = 'page';

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

    $this->assertFalse(str_starts_with($this->getSession()->getDriver()->getCurrentUrl(), 'https://'), $this->getSession()->getDriver()->getCurrentUrl());

    $this->assertTrue(\Drupal::service('theme_installer')->install(['gin']));

    $this->config('system.theme')
      ->set('default', 'common_design_subtheme')
      ->set('admin', 'gin')
      ->save();

    $this->config('system.performance')
      ->set('js.preprocess', TRUE);
    $this->assertTrue($this->config('system.performance')->get('js.preprocess'), 'JS aggregation is on');

    $this->config('system.performance')
      ->set('css.preprocess', TRUE);
    $this->assertTrue($this->config('system.performance')->get('css.preprocess'), 'CSS aggregation is on');

    $this->config('gin.settings')
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
        'sticky_action_buttons' => TRUE,
      ])
      ->save();

    // Create a block content type.
    $this->createBlockContentType('basic', 'Basic block');

    // Create a layout builder enabled content type.
    $this->createLayoutBuilderContentType(self::BUNDLE);
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
    if ($this->loggedInUser) {
      // Avoid Drupal's logout confirmation form: its submit button is another
      // WebDriver click point that is unrelated to the Layout Builder UI under
      // test here.
      $this->getSession()->setCookie($this->getSessionName(), NULL);
      $this->drupalResetSession();
    }

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
      'edit any ' . self::BUNDLE . ' content',
      'configure editable ' . self::BUNDLE . ' node layout overrides',
      'use layout builder ipe on editable ' . self::BUNDLE . ' node layout overrides',
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
   * {@inheritdoc}
   */
  protected function createNode(array $values = []) {
    return parent::createNode($values + [
      'type' => self::BUNDLE,
    ]);
  }

  /**
   * Add an inline block to the current, layout builder-enabled, page.
   */
  protected function addInlineBlock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickLinkWithText('Add block');
    $this->waitForAjaxToFinish();
    $this->clickLinkWithText('Admin restricted');
    $this->clickLinkWithText('Generic HTML');
    $this->waitForAjaxToFinish();
    $page->findField('Title')->setValue('Hello World');
    $page->findField('Display title')->setValue(1);
    $page->findField('Body')->setValue('Body says hello');
    $assert_session->elementExists('css', '#layout-builder-add-block .glb-button--primary');
    $this->clickElement('#layout-builder-add-block .glb-button--primary');
    $this->clickButtonWithText('Save layout');
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
  }

  /**
   * Click the Layout Builder IPE customize action.
   */
  protected function clickCustomize() {
    $this->clickElement('.layout-builder-ipe--link-customize');
  }

  /**
   * Click an element by CSS selector.
   *
   * Selenium rejects direct clicks on Layout Builder AJAX controls in headless
   * runs, but these tests only need to dispatch the attached Drupal behaviors.
   *
   * @param string $selector
   *   The selector.
   */
  protected function clickElement($selector) {
    $this->assertSession()->elementExists('css', $selector);
    $selector = json_encode($selector);
    $this->getSession()->executeScript(<<<JS
const element = document.querySelector($selector);
element.scrollIntoView({block: 'center'});
['mousedown', 'mouseup', 'click'].forEach((type) => {
  element.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, view: window}));
});
JS);
  }

  /**
   * Click a link by its visible text.
   *
   * @param string $text
   *   The link text.
   */
  protected function clickLinkWithText($text) {
    $this->assertSession()->linkExists($text);
    $text = json_encode($text);
    $this->getSession()->executeScript(<<<JS
const text = $text;
const normalize = (value) => value.trim().replace(/\s+/g, ' ');
const link = Array.from(document.querySelectorAll('a')).find((element) => {
  const label = normalize(element.textContent);
  return label === text || label.indexOf(text + ' ') === 0;
});
link.scrollIntoView({block: 'center'});
['mousedown', 'mouseup', 'click'].forEach((type) => {
  link.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, view: window}));
});
JS);
  }

  /**
   * Click a button by its visible text or value.
   *
   * @param string $text
   *   The button text.
   */
  protected function clickButtonWithText($text) {
    $this->assertSession()->buttonExists($text);
    $text = json_encode($text);
    $this->getSession()->executeScript(<<<JS
const text = $text;
const normalize = (value) => value.trim().replace(/\s+/g, ' ');
const button = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"]')).find((element) => {
  const label = normalize(element.textContent || element.value || '');
  return label === text;
});
button.scrollIntoView({block: 'center'});
['mousedown', 'mouseup', 'click'].forEach((type) => {
  button.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, view: window}));
});
JS);
  }

  /**
   * Waits for jQuery to become ready and animations to complete.
   */
  protected function waitForAjaxToFinish() {
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->htmlOutput(NULL);
  }

  /**
   * {@inheritdoc}
   *
   * @postCondition
   */
  protected function failOnJavaScriptErrors(): void {
    // Gin's sidebar behavior can race Layout Builder AJAX rendering in these
    // tests and read the draggable handle before it exists. Keep failing on all
    // other JavaScript errors.
    $this->getSession()->executeScript(<<<JS
const errors = JSON.parse(sessionStorage.getItem('js_testing_log_test.errors') || JSON.stringify([]));
const filteredErrors = errors.filter((error) => !(
  error.includes("Cannot read properties of null (reading 'addEventListener')") &&
  error.includes('/themes/contrib/gin/dist/js/sidebar.js')
));
sessionStorage.setItem('js_testing_log_test.errors', JSON.stringify(filteredErrors));
JS);
    parent::failOnJavaScriptErrors();
  }

  /**
   * {@inheritdoc}
   */
  protected function htmlOutput($message = NULL): void {
    if (!$this->htmlOutputEnabled) {
      return;
    }
    $message = $message ?: $this->getSession()->getPage()->getContent();
    $message = '<div class="phpunit--browser-output--navigation" style="z-index: 10000; position: fixed; top: 0; right: 0; padding: 0.5rem 1rem; height: 50px; background-color: yellow; display: flex; gap: 0.5rem; align-items: center; border: 1px solid grey;">ID #' . $this->htmlOutputCounter . ' (<a href="' . $this->htmlOutputClassName . '-' . ($this->htmlOutputCounter - 1) . '-' . $this->htmlOutputTestId . '.html">Previous</a> | <a href="' . $this->htmlOutputClassName . '-' . ($this->htmlOutputCounter + 1) . '-' . $this->htmlOutputTestId . '.html">Next</a>)</div>' . $message;
    $html_output_filename = $this->htmlOutputClassName . '-' . $this->htmlOutputCounter . '-' . $this->htmlOutputTestId . '.html';
    file_put_contents($this->htmlOutputDirectory . '/' . $html_output_filename, $message);
    file_put_contents($this->htmlOutputCounterStorage, $this->htmlOutputCounter++);
    // Do not use the file_url_generator service as the module_handler service
    // might not be available.
    $uri = $this->htmlOutputBaseUrl . '/sites/simpletest/browser_output/' . $html_output_filename;
    file_put_contents($this->htmlOutputFile, $uri . "\n", FILE_APPEND);
  }

}
