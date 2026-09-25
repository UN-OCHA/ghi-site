<?php

declare(strict_types=1);

namespace Drupal\Tests\ghi_teams\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests cache for teams.
 */
#[Group('ghi_teams')]
class TeamCacheTest extends KernelTestBase {

  use TeamTestTrait;

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

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('system', 'sequences');
    $this->installConfig(['system', 'node', 'taxonomy', 'field']);

    $this->setupTeamVocabularies();
  }

  /**
   * Test that cache tags for a team contain the cache tags of content spaces.
   */
  public function testCacheTagsToInvalidate() {
    $content_space = $this->createContentSpace();
    $team = $this->createTeam([
      'field_content_spaces' => $content_space->id(),
    ]);
    $cache_tags = $team->getCacheTagsToInvalidate();
    $this->assertContains('config:views.view.content', $cache_tags);
    $this->assertContains('taxonomy_term:' . $team->id(), $cache_tags);
    $this->assertTrue(!empty(array_intersect($content_space->getCacheTags(), $cache_tags)));
  }

}
