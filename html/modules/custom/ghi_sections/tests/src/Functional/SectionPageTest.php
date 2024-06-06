<?php

namespace Drupal\Tests\ghi_sections\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the node pages.
 *
 * @group ghi_sections
 */
class SectionPageTest extends BrowserTestBase {

  use BaseObjectTestTrait;
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;
  use SectionTestTrait;
  use LayoutEntityHelperTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'toolbar',
    'admin_toolbar',
    'admin_toolbar_tools',
    'node',
    'field_ui',
    'path',
    'pathauto',
    'gin_lb',
    'layout_builder_ipe',
    'ghi_sections',
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

    $section_type = $this->createSectionType();
    LayoutBuilderEntityViewDisplay::load('node.' . $section_type->id() . '.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->setThirdPartySetting('layout_builder_ipe', 'enabled', TRUE)
      ->save();

    $this->placeSectionNavigationBlock();
    $this->placeSectionMetaDataBlock();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer nodes',
      'bypass node access',
      'create and edit custom blocks',
      'view the administration theme',
      'edit any section content',
      'configure editable section node layout overrides',
      'use layout builder ipe on editable section node layout overrides',
    ]));
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
   * Test the section page.
   */
  public function testSectionPage() {
    $section = $this->createSection();
    $this->drupalGet($section->toUrl()->toString());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#block-sectionnavigation li.active a[href="' . $section->toUrl()->toString() . '#page-title"].active');
  }

}
