<?php

namespace Drupal\Tests\ghi_teams\Traits;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_teams\Entity\ContentSpace;
use Drupal\ghi_teams\Entity\Team;

/**
 * Provides methods to create teams in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait TeamTestTrait {

  use TaxonomyTestTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * Setup vocabularies to be used with teams.
   */
  public function setupTeamVocabularies() {
    $this->createContentSpaceVocabulary();
    $this->createTeamVocabulary();

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        ContentSpace::BUNDLE => ContentSpace::BUNDLE,
      ],
    ];
    $this->createEntityReferenceField('taxonomy_term', Team::BUNDLE, 'field_content_spaces', 'Content spaces', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
  }

  /**
   * Create a vocabulary for teams.
   */
  public function createTeamVocabulary(array $values = []) {
    $team_vocabulary = &drupal_static(__FUNCTION__);
    if (!$team_vocabulary) {
      $team_vocabulary = $this->createVocabulary([
        'vid' => Team::BUNDLE,
        'name' => 'Team',
      ] + $values);
    }
    return $team_vocabulary;
  }

  /**
   * Create a vocabulary for content spaces.
   *
   * @param array $values
   *   Optional values to set.
   *
   * @return \Drupal\ghi_teams\Entity\ContentSpace
   *   The content space term object.
   */
  private function createContentSpaceVocabulary(array $values = []) {
    $content_space_vocabulary = &drupal_static(__FUNCTION__);
    if (!$content_space_vocabulary) {
      $content_space_vocabulary = $this->createVocabulary([
        'vid' => ContentSpace::BUNDLE,
        'name' => 'Content space',
      ] + $values);
    }
    return $content_space_vocabulary;
  }

  /**
   * Create a team taxonomy term.
   */
  public function createTeam(array $values = []) {
    // Create team vocabulary and fields.
    $vocabulary = $this->createTeamVocabulary();
    $team = $this->createTerm($vocabulary, $values);
    return $team;
  }

  /**
   * Create a content space taxonomy term.
   */
  public function createContentSpace(array $values = []) {
    // Create team vocabulary and fields.
    $vocabulary = $this->createContentSpaceVocabulary();
    $content_space = $this->createTerm($vocabulary, $values);
    return $content_space;
  }

}
