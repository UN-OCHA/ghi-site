<?php

namespace Drupal\Tests\ghi_content\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_teams\Entity\ContentSpace;
use Drupal\ghi_teams\Entity\Team;

/**
 * Tests the content space based access logic for content.
 *
 * @group ghi_content
 */
class ContentSpaceNodeAccessTest extends BrowserTestBase {

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
    'ghi_content_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The content bundle to use for the tests.
   *
   * This content type is part of the standard installation profile.
   */
  private const BUNDLE = 'article';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupTeamVocabularies();
    $this->setupContent();
  }

  /**
   * Tests access for nodes based on content space association.
   */
  public function testNodeAccessByContentSpace() {
    // Create a content space to be assigned to the team and the node.
    $content_space = $this->createContentSpace();

    // Create a team term and assign it to user and content.
    $team = $this->createTeam([
      'field_content_spaces' => $content_space->id(),
    ]);

    $node = $this->createNode([
      'type' => self::BUNDLE,
      'field_content_space' => $content_space->id(),
    ]);

    $permissions = [
      'administer nodes',
    ];
    $this->drupalLogin($this->drupalCreateUser($permissions, NULL, FALSE, [
      'field_team' => $team->id(),
    ]));
    $assert_session = $this->assertSession();

    // Edit the node and confirm the node edit form can be loaded.
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(200);

    // Now try with a user who is not associated to the content space.
    $this->drupalLogin($this->drupalCreateUser($permissions, NULL, FALSE, [
      'field_team' => $this->createTeam(),
    ]));

    // Edit the node and confirm the node edit form cannot be loaded.
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(403);
  }

  /**
   * Test the content listing.
   */
  public function testArticleListing() {
    // Create a content space to be assigned to the team and the node.
    $content_space = $this->createContentSpace();

    // Create a team term and assign it to user and content.
    $team = $this->createTeam([
      'field_content_spaces' => $content_space->id(),
    ]);

    $node = $this->createNode([
      'type' => self::BUNDLE,
      'field_content_space' => $content_space->id(),
    ]);

    $permissions = [
      'administer nodes',
      'access content overview',
    ];
    $user = $this->drupalCreateUser($permissions, NULL, FALSE, [
      'field_team' => $team->id(),
    ]);
    $this->drupalLogin($user);
    $assert_session = $this->assertSession();

    // Go to the node listing and confirm that we can see and edit the article.
    $this->drupalGet('admin/content');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementTextContains('css', 'table td.views-field-title', $node->label());
    $assert_session->elementTextContains('css', 'table td.views-field-operations', 'Edit');
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(200);

    // Update the team and remove the content space reference. Confirm that the
    // node is still visible not can't be edited.
    $team->set('field_content_spaces', NULL)->save();
    $this->drupalGet('admin/content');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementTextContains('css', 'table td.views-field-title', $node->label());
    $assert_session->elementTextNotContains('css', 'table td.views-field-operations', 'Edit');
    $this->drupalGet($node->toUrl('edit-form')->toString());
    $assert_session->statusCodeEquals(403);

    // Add the content space again and confirm that the current user can edit
    // content.
    $team->set('field_content_spaces', $content_space->id())->save();
    $this->drupalGet('admin/content');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementTextContains('css', 'table td.views-field-title', $node->label());
    $assert_session->elementTextContains('css', 'table td.views-field-operations', 'Edit');

    // Remove the team from the user and confirm that the user can't edit the
    // content.
    $user->set('field_team', NULL)->save();
    $this->drupalLogin($user);
    $this->drupalGet('admin/content');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementTextContains('css', 'table td.views-field-title', $node->label());
    $assert_session->elementTextNotContains('css', 'table td.views-field-operations', 'Edit');
  }

  /**
   * Setup content types and content for these tests.
   */
  private function setupContent() {
    $this->drupalCreateContentType([
      'type' => self::BUNDLE,
      'title' => 'Article',
    ]);

    // Create an entity reference field for nodes and users.
    $handler_settings = [
      'target_bundles' => [
        Team::BUNDLE => Team::BUNDLE,
      ],
    ];
    $this->createEntityReferenceField('node', self::BUNDLE, 'field_content_space', 'Content space', 'taxonomy_term', 'default', [
      'target_bundles' => [
        ContentSpace::BUNDLE => ContentSpace::BUNDLE,
      ],
    ]);
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', self::BUNDLE)
      ->setComponent('field_content_space', ['type' => 'options_select'])
      ->save();
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', self::BUNDLE)
      ->setComponent('field_content_space', ['type' => 'entity_reference_label'])
      ->save();

    $this->createEntityReferenceField('user', 'user', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
  }

}
