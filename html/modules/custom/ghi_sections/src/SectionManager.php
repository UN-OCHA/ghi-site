<?php

namespace Drupal\ghi_sections;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Sync element service class.
 */
class SectionManager {

  /**
   * The machine name of the bundle to use for articles.
   */
  const SECTION_BUNDLES = ['section', 'global_section'];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a section create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load a section for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object of the section to load.
   * @param int $year
   *   An optional year.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The section node if one could be found.
   */
  public function loadSectionForBaseObject(BaseObjectInterface $base_object, $year = NULL) {
    if ($base_object->needsYear() && $year === NULL) {
      throw new \InvalidArgumentException(sprintf('Invalid arguments. The base object of type "%s" needs a year, but none has been given.', $base_object->getEntityType()->getLabel()));
    }

    $properties = [
      'type' => 'section',
      'field_base_object' => $base_object->id(),
    ];

    if ($base_object->needsYear()) {
      $properties['field_year'] = $year;
    }
    $sections = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
    return count($sections) ? reset($sections) : NULL;
  }

  /**
   * Load all sections assigned to the given team term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term for the team.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of sections assigned to the team.
   */
  public function loadSectionsForTeam(TermInterface $term) {
    $sections = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'section',
      'field_team' => $term->id(),
    ]);
    return $sections;
  }

}
