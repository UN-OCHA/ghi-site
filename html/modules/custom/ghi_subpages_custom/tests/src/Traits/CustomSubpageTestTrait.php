<?php

namespace Drupal\Tests\ghi_subpages_custom\Traits;

use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_subpages_custom\Entity\CustomSubpage;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Provides methods to create custom subpages in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait CustomSubpageTestTrait {

  use ContentTypeCreationTrait;
  use SectionTestTrait;
  use SubpageTestTrait;
  use TeamTestTrait;
  use FieldTestTrait;
  use EntityReferenceTestTrait;

  const CUSTOM_SUBPAGE_BUNDLE = 'custom_subpage';

  /**
   * Create the content types for testing custom subpages.
   */
  public function createCustomSubpageContentTypes() {
    $this->createSubpageContentTypes();

    // Create the content type for the custom subpage.
    $this->createContentType([
      'type' => self::CUSTOM_SUBPAGE_BUNDLE,
      'name' => ucfirst(self::CUSTOM_SUBPAGE_BUNDLE),
    ]);
    $this->createEntityReferenceField('node', self::CUSTOM_SUBPAGE_BUNDLE, 'field_entity_reference', 'Section', 'node', 'default', [
      'target_bundles' => [self::SECTION_BUNDLE],
    ]);

    $this->createEntityReferenceField('node', self::CUSTOM_SUBPAGE_BUNDLE, 'field_team', 'Team', 'taxonomy_term', 'default', [
      'target_bundles' => ['team'],
    ]);
  }

  /**
   * Create a custom subpage node.
   *
   * @return \Drupal\ghi_subpages_custom\Entity\CustomSubpage
   *   A custom subpage node.
   */
  protected function createCustomSubpage(Section $section) {
    $custom_subpage = CustomSubpage::create([
      'type' => self::CUSTOM_SUBPAGE_BUNDLE,
      'title' => $this->randomString(),
      'uid' => 0,
      'field_entity_reference' => [
        'target_id' => $section->id(),
      ],
    ]);
    $custom_subpage->save();
    return $custom_subpage;
  }

}
