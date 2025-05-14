<?php

namespace Drupal\Tests\ghi_teams\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_teams\Entity\Team;

/**
 * Tests the team based access logic for nodes.
 *
 * @group ghi_teams
 */
class TeamNodeAccessTest extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;
  use EntityViewTrait;
  use TeamTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_teams',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  private const BUNDLE = 'section';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupTeamVocabularies();
    $this->setupContent();
  }

  /**
   * Tests access for nodes based on team association.
   *
   * @covers ghi_teams_form_alter
   */
  public function testNodeAccessByTeam() {
    // Create a team term and assign it to user and content.
    $team = $this->createTeam([
      'name' => 'team 1',
    ]);

    $node = $this->createNode([
      'type' => self::BUNDLE,
      'field_team' => $team->id(),
    ]);

    $permissions = [
      'administer nodes',
    ];
    $this->drupalLogin($this->drupalCreateUser($permissions, NULL, FALSE, [
      'field_team' => $team->id(),
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Edit the node and confirm the node edit form can be loaded.
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(200);
    // The team field should be disabled and a message should have been added.
    $assert_session->elementExists('css', '.form-item-field-team.form-disabled');
    $assert_session->elementTextContains('css', '.form-item-field-team.form-disabled', 'You do not have permission to change the team for this section');

    // Now try with a user who is not associated to the team.
    $this->drupalLogin($this->drupalCreateUser($permissions));

    // Edit the node and confirm the node edit form cannot be loaded.
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(403);

    // Now try with a user associated to the team and who can administer teams.
    $this->drupalLogin($this->drupalCreateUser(array_merge($permissions, ['administer teams']), NULL, FALSE, [
      'field_team' => $team->id(),
    ]));
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(200);
    // The team field should not be disabled.
    $field_team = $page->find('css', '.form-item-field-team');
    $classes = $field_team->getAttribute('class');
    $this->assertStringNotContainsString('form-disabled', $classes);
  }

  /**
   * Setup content types and content for these tests.
   */
  private function setupContent() {
    // Create a section content type.
    $this->createContentType([
      'type' => self::BUNDLE,
    ]);

    // Create an entity reference field for nodes and users.
    $handler_settings = [
      'target_bundles' => [
        Team::BUNDLE => Team::BUNDLE,
      ],
    ];
    $this->createEntityReferenceField('node', self::BUNDLE, 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', self::BUNDLE)
      ->setComponent('field_team', ['type' => 'options_select'])
      ->save();
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', self::BUNDLE)
      ->setComponent('field_team', ['type' => 'entity_reference_label'])
      ->save();

    $this->createEntityReferenceField('user', 'user', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
  }

}
