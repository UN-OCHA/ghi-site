<?php

namespace Drupal\Tests\ghi_homepage\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the node wizard pages.
 *
 * @group ghi_homepage
 */
class WizardTest extends BrowserTestBase {

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
    'gin_lb',
    'ghi_homepage',
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
      ->getEditable('node.settings')
      ->set('use_admin_theme', TRUE)
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
        'sticky_action_buttons' => TRUE,
      ])
      ->save();

    $this->setupContent();

    // Create a user with permission to view the administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer themes',
      'access toolbar',
      'access administration pages',
      'administer nodes',
      'bypass node access',
      'view the administration theme',
    ]));

    \Drupal::service('router.builder')->rebuildIfNeeded();
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
   * Tests homepage wizard page.
   */
  public function testHomepageWizard() {
    $this->drupalGet('/admin/appearance');

    $this->drupalGet('/node/add/' . Homepage::BUNDLE);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('No teams found. You must import teams before sections can be created.');
    $this->assertSession()->pageTextContains('Create Homepage');
    $this->assertSession()->fieldExists('Year');
    $this->assertSession()->buttonExists('Next');

    $page = $this->getSession()->getPage();
    $page->fillField('Year', 2023);
    $page->pressButton('Next');

    $this->assertSession()->fieldExists('Team');
    $this->assertSession()->buttonExists('Back');
    $this->assertSession()->buttonExists('Next');
    $page->pressButton('Next');

    $this->assertSession()->fieldExists('Title');
    $this->assertSession()->buttonExists('Back');
    $this->assertSession()->buttonExists('Create Homepage');
    $page->fillField('Title', '2023');
    $page->pressButton('Create Homepage');

    $this->assertSession()->pageTextContains('Created Homepage for 2023');
  }

  /**
   * Test that the homepages must have unique years.
   */
  public function testHomepageUniquePerYear() {
    $team = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'team',
    ])->save();

    Node::create([
      'type' => Homepage::BUNDLE,
      'title' => '2023',
      'field_year' => '2023',
      'field_team' => $team,
    ])->save();

    $this->drupalGet('/node/add/' . Homepage::BUNDLE);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('Year');
    $this->assertSession()->buttonExists('Next');

    $page = $this->getSession()->getPage();
    $page->fillField('Year', 2023);
    $page->pressButton('Next');

    $this->assertSession()->pageTextContains('A homepage for 2023 already exists.');

    $page->fillField('Year', 2024);
    $page->pressButton('Next');
    $this->assertSession()->pageTextNotContains('A homepage for 2023 already exists.');
  }

  /**
   * Setup content types and content for these tests.
   */
  private function setupContent() {
    $this->drupalCreateContentType([
      'type' => Homepage::BUNDLE,
      'name' => 'Homepage',
    ]);

    $this->createField('node', Homepage::BUNDLE, 'integer', 'field_year', 'Year');

    // Create team vocabulary and fields.
    Vocabulary::create([
      'vid' => 'team',
      'name' => 'Team',
    ])->save();
    $handler_settings = [
      'target_bundles' => [
        'team' => 'team',
      ],
    ];
    $this->createEntityReferenceField('node', Homepage::BUNDLE, 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'team',
    ])->save();
  }

}
