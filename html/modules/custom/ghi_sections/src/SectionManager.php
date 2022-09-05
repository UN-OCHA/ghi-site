<?php

namespace Drupal\ghi_sections;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Helpers\StringHelper;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Drupal\taxonomy\TermInterface;

/**
 * Sync element service class.
 */
class SectionManager {

  use DependencySerializationTrait;
  use ShortNameTrait;

  /**
   * The machine name of the bundle to use for articles.
   */
  const SECTION_BUNDLES = ['section', 'global_section', 'homepage'];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a section create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->currentUser = $user;
  }

  /**
   * Get the available base object types.
   *
   * @return array
   *   An array of base object types.
   */
  public function getAvailableBaseObjectTypes() {
    // Find out what base objects types can be referenced.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'section');
    if (!array_key_exists('field_base_object', $fields)) {
      // Bail out.
      return FALSE;
    }
    /** @var \Drupal\field\Entity\FieldConfig $base_object_field_config */
    $base_object_field_config = $fields['field_base_object'];
    $allowed_base_object_types = $base_object_field_config->getSetting('handler_settings')['target_bundles'];

    // Then get the list of available base object types and filter it by the
    // allowed ones.
    $base_object_types = $this->entityTypeManager->getStorage('base_object_type')->loadMultiple();
    $base_object_types = array_filter($base_object_types, function ($type) use ($allowed_base_object_types) {
      return in_array($type->id(), $allowed_base_object_types);
    });
    return $base_object_types;
  }

  /**
   * Create a section node for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object that should be connected to the section.
   * @param array $values
   *   A semi-optional array of key value pairs, allowing to specify some
   *   initial values. Most of them can be inferred from the base object if not
   *   explicitly given.
   *
   * @return \Drupal\Core\Entity\EntityInterface|bool
   *   Either the created section entity, or boolean FALSE.
   */
  public function createSectionForBaseObject(BaseObjectInterface $base_object, array $values) {
    $status = FALSE;
    $base_object_type = $base_object->type->entity;
    if ($base_object_type->needsYearForDataRetrieval() && empty($values['year']) || empty($values['team'])) {
      return FALSE;
    }
    $tags = $values['tags'] ?? $this->getDefaultTagsFromBaseObject($base_object);
    if (empty($tags)) {
      return FALSE;
    }
    $section = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'section',
      'title' => $values['title'] ?? $base_object->label(),
      'uid' => $this->currentUser->id(),
      'status' => FALSE,
    ]);
    $section->field_base_object->entity = $base_object;
    if ($base_object_type->needsYearForDataRetrieval()) {
      $section->field_year = $values['year'];
    }
    $section->field_team = $values['team'];
    $section->field_tags = $tags;
    if ($base_object instanceof Plan) {
      // Make sure that plan sections have a chance to display their image.
      $section->field_hero_image = [
        'source' => 'hpc_webcontent_file_attachment',
      ];
    }
    $status = $section->save();
    return $status && $section ? $section : FALSE;
  }

  /**
   * Get the default tags for a section.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object from which tags should be extracted.
   *
   * @return array
   *   An array of tags as strings.
   */
  private function getDefaultTagsFromBaseObject(BaseObjectInterface $base_object) {
    $tags = [];
    switch ($base_object->bundle()) {
      case 'plan':
        $shortname = $base_object->get('field_short_name')->value;
        if ($shortname) {
          $tags[] = $shortname;
        }
        $tags[] = $base_object->get('field_year')->value;
        $tags[] = StringHelper::getAbbreviation($base_object->get('field_plan_type')->entity->label());
        break;

      default:
        // Not yet implemented.
        break;

    }
    $tags = array_filter($tags);
    if (empty($tags)) {
      return $tags;
    }
    foreach ($tags as &$tag) {
      $term = TaxonomyHelper::loadTermByName($tag, 'tags');
      if (!$term) {
        // Create the tag.
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
          'vid' => 'tags',
          'name' => $tag,
        ]);
        $term->save();
      }
      $tag = [
        'target_id' => $term->id(),
      ];
    }
    return $tags;
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
