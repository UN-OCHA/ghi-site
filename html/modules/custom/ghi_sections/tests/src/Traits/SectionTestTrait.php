<?php

namespace Drupal\Tests\ghi_sections\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\Menu\SectionMenuStorage;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_teams\Traits\TeamTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Provides methods to create sections in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait SectionTestTrait {

  use BaseObjectTestTrait;
  use TeamTestTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;
  use ContentTypeCreationTrait;

  const SECTION_BUNDLE = 'section';

  /**
   * Create a section type.
   */
  public function createSectionType() {
    $this->createBaseObjectType([
      'id' => 'plan',
      'label' => 'Plan',
      'hasYear' => TRUE,
    ]);

    // Create a team.
    $this->createTeamVocabulary();

    // Create tags vocabulary.
    $this->createVocabulary([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);

    // Create the section bundle.
    $this->createContentType([
      'type' => self::SECTION_BUNDLE,
      'name' => ucfirst(self::SECTION_BUNDLE),
    ]);

    $this->createEntityReferenceField('node', self::SECTION_BUNDLE, 'field_base_object', 'Base object', 'base_object', 'default', [
      'target_bundles' => ['plan'],
    ]);

    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('node', self::SECTION_BUNDLE)
      ->setComponent('field_base_object', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->save();

    $this->createEntityReferenceField('node', 'section', 'field_team', 'Team', 'taxonomy_term', 'default', [
      'target_bundles' => ['team'],
    ]);

    $this->createEntityReferenceField('node', 'section', 'field_tags', 'Tags', 'taxonomy_term', 'default', [
      'target_bundles' => ['tags'],
    ]);

    $section_menu_storage = $this->container->get('ghi_sections.section_menu.storage');
    $section_menu_storage->addSectionMenuField(self::SECTION_BUNDLE);
    $this->assertTrue($this->bundleHasField(self::SECTION_BUNDLE, SectionMenuStorage::FIELD_NAME));
  }

  /**
   * Create a section.
   */
  public function createSection(array $values = []) {
    $values += [
      'type' => self::SECTION_BUNDLE,
      'title' => $this->randomString(),
    ];
    if (empty($values['field_base_object'])) {
      $base_object = $this->createBaseObject([
        'type' => 'plan',
      ]);
      $values['field_base_object'] = [
        'target_id' => $base_object->id(),
      ];
    }
    if (empty($values['field_team'])) {
      $team = $this->createTeam();
      $values['field_team'] = [
        'target_id' => $team->id(),
      ];
    }
    $section = Section::create($values);
    $this->assertSame(SAVED_NEW, $section->save());
    $this->assertInstanceOf(SectionNodeInterface::class, $section);
    $this->assertInstanceOf(BaseObjectInterface::class, $section->getBaseObject());
    return $section;
  }

}
