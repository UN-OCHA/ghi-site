<?php

namespace Drupal\Tests\ghi_sections\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\BasicObjectTypeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the node wizard pages.
 *
 * @group ghi_sections
 */
class WizardTest extends BrowserTestBase {

  use BasicObjectTypeCreationTrait;
  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_sections',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupContent();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
    ]));
  }

  /**
   * Tests that the wizard pages can be accessed.
   */
  public function testSectionWizard() {
    $this->drupalGet('/node/add/section');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('No base objects available to create a section.');
    $this->assertSession()->pageTextNotContains('No teams found. You must import teams before sections can be created.');
    $this->assertSession()->pageTextContains('Select a section type.');
    $this->assertSession()->buttonExists('Next');
  }

  /**
   * Tests that the wizard pages can be accessed.
   */
  public function testGlobalSectionWizard() {
    $this->drupalGet('/node/add/global_section');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('No teams found. You must import teams before sections can be created.');
    $this->assertSession()->pageTextContains('Enter a year for this global section');
    $this->assertSession()->buttonExists('Next');
  }

  /**
   * Setup content types and content for these tests.
   */
  private function setupContent() {
    $this->createBaseObjectType([
      'id' => 'plan',
      'label' => 'Plan',
      'hasYear' => TRUE,
    ]);
    $this->drupalCreateContentType([
      'type' => 'section',
      'name' => 'Section',
    ]);
    $this->drupalCreateContentType([
      'type' => 'global_section',
      'name' => 'Global section',
    ]);

    $handler_settings = [
      'target_bundles' => ['plan'],
    ];
    $this->createEntityReferenceField('node', 'section', 'field_base_object', 'Base object', 'base_object', 'default', $handler_settings);

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
    $this->createEntityReferenceField('node', 'section', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    $this->createEntityReferenceField('node', 'global_section', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'team',
    ])->save();

    // Setup vocabulary.
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    $handler_settings = [
      'target_bundles' => [
        'tags' => 'tags',
      ],
    ];
    $this->createEntityReferenceField('node', 'section', 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings);
    $this->createEntityReferenceField('node', 'global_section', 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings);
  }

}
