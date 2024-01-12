<?php

namespace Drupal\Tests\ghi_embargoed_access\Functional;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_access_password\Service\PasswordAccessManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the access to embargoed content.
 *
 * @group ghi_embargoed_access
 */
class EmbargoedNodeAccessTest extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;
  use StringTranslationTrait;
  use TaxonomyTestTrait;

  const BUNDLE_PAGE = 'page';
  const BUNDLE_SECTION = 'section';
  const BUNDLE_SUBPAGE = 'financials';
  const BUNDLE_ARTICLE = 'article';

  const BUNDLES = [
    self::BUNDLE_PAGE,
    self::BUNDLE_SECTION,
    self::BUNDLE_SUBPAGE,
    self::BUNDLE_ARTICLE,
  ];

  const FIELD_NAME_PROTECTED = 'field_protected';
  const FIELD_NAME_SECTION_REFERENCE = 'field_entity_reference';
  const FIELD_NAME_TAG = 'field_tags';

  const PASSWORD = 'password';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_embargoed_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $password;

  /**
   * The display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $displayRepository;

  /**
   * A vocabulary for tags.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $tagVocabulary;

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->password = $this->container->get('password');
    $this->displayRepository = $this->container->get('entity_display.repository');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->setupConfiguration();
    $this->createFieldStorage();
    $this->setupContent();

    // Create an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer ghi embargoed access config',
    ]);
  }

  /**
   * Setup the configuration.
   */
  private function setupConfiguration() {
    $this->config('entity_access_password.settings')->set('global_password', $this->password->hash(self::PASSWORD));
    $this->config('entity_access_password.settings')->save();
    $this->config('ghi_embargoed_access.settings')->set('enabled', TRUE);
    $this->config('ghi_embargoed_access.settings')->save();
  }

  /**
   * Create the field storage.
   */
  protected function createFieldStorage(): void {
    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME_PROTECTED,
      'entity_type' => 'node',
      'type' => 'entity_access_password_password',
      'settings' => [],
      'cardinality' => 1,
    ])->save();
  }

  /**
   * Setup the content.
   */
  private function setupContent() {
    $this->tagVocabulary = $this->createVocabulary();
    foreach (self::BUNDLES as $bundle) {
      $this->drupalCreateContentType([
        'type' => $bundle,
        'name' => ucfirst($bundle),
      ]);
      FieldConfig::create([
        'field_name' => self::FIELD_NAME_PROTECTED,
        'label' => 'Entity access password',
        'entity_type' => 'node',
        'bundle' => $bundle,
        'required' => FALSE,
        'settings' => [
          'password_entity' => FALSE,
          'password_bundle' => FALSE,
          'password_global' => TRUE,
          'password' => '',
          'view_modes' => [
            'full' => 'full',
            'teaser' => 'teaser',
          ],
        ],
      ])->save();

      if ($bundle == self::BUNDLE_SUBPAGE) {
        $this->createEntityReferenceField('node', $bundle, self::FIELD_NAME_SECTION_REFERENCE, 'Section', 'node', 'default', [
          'target_bundles' => [self::BUNDLE_SECTION],
        ]);
      }

      if ($bundle == self::BUNDLE_ARTICLE || $bundle == self::BUNDLE_SECTION) {
        $this->createEntityReferenceField('node', $bundle, self::FIELD_NAME_TAG, 'Tags', 'taxonomy_term', 'default', [
          'target_bundles' => [$this->tagVocabulary->id()],
        ]);
      }

      $this->displayRepository->getFormDisplay('node', $bundle)
        ->setComponent(self::FIELD_NAME_PROTECTED, [
          'type' => 'entity_access_password_password',
          'settings' => [
            'open' => FALSE,
            'show_entity_title' => 'optional',
            'show_hint' => 'optional',
            'allow_random_password' => TRUE,
          ],
        ])
        ->save();

      $this->displayRepository->getViewDisplay('node', $bundle, PasswordAccessManagerInterface::PROTECTED_VIEW_MODE)
        ->setComponent(self::FIELD_NAME_PROTECTED, [
          'type' => 'entity_access_password_form',
          'settings' => [
            'help_text' => 'Help text: ' . $bundle,
          ],
        ])
        ->save();

      $this->displayRepository->getViewDisplay('node', $bundle)
        ->removeComponent(self::FIELD_NAME_PROTECTED)
        ->save();
      $this->displayRepository->getViewDisplay('node', $bundle, 'full')
        ->removeComponent(self::FIELD_NAME_PROTECTED)
        ->save();
      $this->displayRepository->getViewDisplay('node', $bundle, 'teaser')
        ->removeComponent(self::FIELD_NAME_PROTECTED)
        ->save();
    }
  }

  /**
   * Test protection of a simple page.
   */
  public function testProtectSimplePage() {
    $node = $this->drupalCreateNode([
      'type' => self::BUNDLE_PAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open page node and confirm it's protected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      $this->t('Submit'),
      'entity-access-password-password-node-' . $node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $node->id());
  }

  /**
   * Test protection of a simple page.
   */
  public function testGlobalEmbargoSwitch() {
    $node = $this->drupalCreateNode([
      'type' => self::BUNDLE_PAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open page node and confirm it's protected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $node->id());

    // Switch global embargo contronl off.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/ghi/embargoed-access');
    $this->getSession()->getPage()->uncheckField('enabled');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->drupalLogout();

    // Open the same page node again and confirm the protection is disabled.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $node->id());
  }

  /**
   * Test protection of a single section page.
   */
  public function testProtectSectionPage() {
    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open section node and confirm it's not protected.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      $this->t('Submit'),
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

  /**
   * Test protection of a section and it's subpages.
   */
  public function testProtectSectionSubpage() {
    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create non-protected subpage node.
    $subpage_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SUBPAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_SECTION_REFERENCE => [
        'target_id' => $section_node->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => FALSE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open section node and confirm it's protected, don't enter password yet.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open subpage node and confirm it's protected by the section.
    $this->drupalGet($subpage_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      $this->t('Submit'),
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

  /**
   * Test protection of a section and articles accessed via the section.
   */
  public function testProtectSectionArticlePage() {
    $tag = $this->createTerm($this->tagVocabulary);
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');

    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_TAG => [
        'target_id' => $tag->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create path aliases.
    $alias_storage->create([
      'path' => $section_node->toUrl()->toString(),
      'alias' => '/plan/1',
    ])->save();

    // Create non-protected subpage node.
    $article_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_ARTICLE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_TAG => [
        'target_id' => $tag->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => FALSE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create path aliases.
    $alias_storage->create([
      'path' => $article_node->toUrl()->toString(),
      'alias' => '/article/1',
    ])->save();

    // Open section node and confirm it's protected, don't enter password yet.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open standalone article node and confirm it's not protected.
    $this->drupalGet($article_node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $article_node->id());

    // Open section specific article node and confirm it's protected by the
    // section.
    $this->drupalGet('/plan/1' . $article_node->toUrl()->toString());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      $this->t('Submit'),
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

}
