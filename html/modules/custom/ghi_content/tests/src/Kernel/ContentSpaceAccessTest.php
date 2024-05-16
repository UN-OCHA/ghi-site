<?php

declare(strict_types=1);

namespace Drupal\Tests\ghi_content\Unit;

use Drupal\ghi_teams\Entity\ContentSpace;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests node access records and grants.
 *
 * @group ghi_content
 */
class ContentSpaceAccessTest extends KernelTestBase {

  use TeamTestTrait;
  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use UserCreationTrait;

  private const BUNDLE = 'article';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'layout_builder',
    'layout_discovery',
    'migrate',
    'text',
    'filter',
    'file',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'ghi_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'pathauto']);

    $this->setupTeamVocabularies();

    NodeType::create(['type' => self::BUNDLE])->save();
    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        ContentSpace::BUNDLE => ContentSpace::BUNDLE,
      ],
    ];
    $this->createEntityReferenceField('node', self::BUNDLE, 'field_content_space', 'Content space', 'taxonomy_term', 'default', $handler_settings);

    $this->createEntityReferenceField('user', 'user', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
  }

  /**
   * Test node access records.
   *
   * @covers ::ghi_content_node_access_records
   */
  public function testNodeAccessRecord() {
    $content_space = $this->createContentSpace();
    $node = $this->createNode([
      'type' => self::BUNDLE,
      'field_content_space' => $content_space->id(),
    ]);
    $grants = ghi_content_node_access_records($node);

    $expected_grants = [];
    $expected_grants[] = [
      'realm' => 'ghi_content_access',
      'gid' => 0,
      'grant_view' => TRUE,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 0,
    ];
    $expected_grants[] = [
      'realm' => 'ghi_content_access',
      'gid' => $content_space->id(),
      'grant_view' => 1,
      'grant_update' => 1,
      'grant_delete' => 0,
      'priority' => 0,
    ];
    $this->assertEquals($expected_grants, $grants);
  }

  /**
   * Test node access grants.
   *
   * @covers ::ghi_content_node_grants
   */
  public function testNodeAccessGrants() {
    $content_space = $this->createContentSpace();
    $team = $this->createTeam([
      'field_content_spaces' => $content_space->id(),
    ]);
    $account = $this->createUser([], NULL, FALSE, [
      'field_team' => $team->id(),
    ]);
    $grants = ghi_content_node_grants($account, 'update');
    $expected_grants = [
      'ghi_content_access' => [0, $content_space->id()],
    ];
    $this->assertEquals($expected_grants, $grants);
  }

}
