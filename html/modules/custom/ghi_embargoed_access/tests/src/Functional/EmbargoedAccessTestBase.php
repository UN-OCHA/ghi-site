<?php

namespace Drupal\Tests\ghi_embargoed_access\Functional;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\entity_access_password\Service\PasswordAccessManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides isolated fixtures for embargoed content access tests.
 *
 * @group ghi_embargoed_access
 */
abstract class EmbargoedAccessTestBase extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;

  const BUNDLE_PAGE = 'page';
  const BUNDLE_SECTION = 'section';
  const BUNDLE_SUBPAGE = 'financials';
  const BUNDLE_ARTICLE = 'article';

  const BUNDLES = [];

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
    foreach (static::BUNDLES as $bundle) {
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

}
