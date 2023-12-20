<?php

namespace Drupal\Tests\ghi_homepage\Functional;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the node wizard pages.
 *
 * @group ghi_sections
 */
class WizardTest extends BrowserTestBase {

  use BaseObjectTestTrait;
  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_homepage',
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
   * Tests homepage wizard page.
   */
  public function testHomepageWizard() {
    $this->drupalGet('/node/add/homepage');
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
      'type' => 'homepage',
      'title' => '2023',
      'field_year' => '2023',
      'field_team' => $team,
    ])->save();

    $this->drupalGet('/node/add/homepage');
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
      'type' => 'homepage',
      'name' => 'Homepage',
    ]);

    $this->createField('node', 'homepage', 'integer', 'field_year', 'Year');

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
    $this->createEntityReferenceField('node', 'homepage', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'team',
    ])->save();
  }

}
