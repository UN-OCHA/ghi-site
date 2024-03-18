<?php

declare(strict_types=1);

namespace Drupal\Tests\ghi_teams\Unit;

use Drupal\ghi_teams\Entity\Team;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests node access records and grants.
 *
 * @group ghi_teams
 */
class TeamNodeAccessTest extends KernelTestBase {

  use TeamTestTrait;
  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use UserCreationTrait;

  private const BUNDLE = 'section';

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
    'filter',
    'text',
    'ghi_teams',
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
    $this->installConfig(['system', 'node', 'taxonomy', 'field']);

    $this->setupTeamVocabularies();

    NodeType::create(['type' => self::BUNDLE])->save();
    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        Team::BUNDLE => Team::BUNDLE,
      ],
    ];
    $this->createEntityReferenceField('node', self::BUNDLE, 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);

    $this->createEntityReferenceField('user', 'user', 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
  }

  /**
   * Test node access records.
   *
   * @covers ::ghi_teams_node_access_records
   */
  public function testNodeAccessRecord() {
    $team = $this->createTeam();
    $node = $this->createNode([
      'type' => self::BUNDLE,
      'field_team' => $team->id(),
    ]);
    $grants = ghi_teams_node_access_records($node);

    $expected_grants = [];
    $expected_grants[] = [
      'realm' => 'ghi_teams_node_access',
      'gid' => 0,
      'grant_view' => TRUE,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 0,
    ];
    $expected_grants[] = [
      'realm' => 'ghi_teams_node_access',
      'gid' => $team->id(),
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
   * @covers ::ghi_teams_node_grants
   */
  public function testNodeAccessGrants() {
    $team = $this->createTeam();
    $account = $this->createUser([], NULL, FALSE, [
      'field_team' => $team->id(),
    ]);
    $grants = ghi_teams_node_grants($account, 'update');
    $expected_grants = [
      'ghi_teams_node_access' => [0, $team->id()],
    ];
    $this->assertEquals($expected_grants, $grants);
  }

}
