<?php

namespace Drupal\Tests\ghi_subpages\Traits;

use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Provides methods to create subpages in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait SubpageTestTrait {

  use ContentTypeCreationTrait;
  use SectionTestTrait;
  use TeamTestTrait;
  use FieldTestTrait;
  use EntityReferenceTestTrait;

  const SUBPAGE_BUNDLES = [
    'population',
    'financials',
    'presence',
    'logframe',
    'progress',
  ];

  /**
   * Create the content types for testing subpages.
   */
  public function createSubpageContentTypes() {
    $this->createSectionType();

    // Create a team.
    $this->createTeamVocabulary();

    // Create the content types for the subpage types.
    foreach (self::SUBPAGE_BUNDLES as $bundle) {
      $this->createContentType([
        'type' => $bundle,
        'name' => ucfirst($bundle),
      ]);
      $this->createEntityReferenceField('node', $bundle, 'field_entity_reference', 'Section', 'node', 'default', [
        'target_bundles' => [self::SECTION_BUNDLE],
      ]);

      $this->createEntityReferenceField('node', $bundle, 'field_team', 'Team', 'taxonomy_term', 'default', [
        'target_bundles' => ['team'],
      ]);
    }
  }

}
