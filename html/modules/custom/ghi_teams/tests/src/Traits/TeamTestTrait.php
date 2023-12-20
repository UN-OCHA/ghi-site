<?php

namespace Drupal\Tests\ghi_teams\Traits;

use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Provides methods to create teams in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait TeamTestTrait {

  use TaxonomyTestTrait;

  /**
   * Create a vocabulary for teams.
   */
  public function createTeamVocabulary() {
    $team_vocabulary = &drupal_static(__FUNCTION__);
    if (!$team_vocabulary) {
      $team_vocabulary = $this->createVocabulary([
        'vid' => 'team',
        'name' => 'Team',
      ]);
    }
    return $team_vocabulary;
  }

  /**
   * Create a team taxonomy term.
   */
  public function createTeam($name = NULL) {
    // Create team vocabulary and fields.
    $vocabulary = $this->createTeamVocabulary();
    $team = $this->createTerm($vocabulary, [
      'name' => $name ?? $this->randomMachineName(),
    ]);
    return $team;
  }

}
