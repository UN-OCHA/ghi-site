<?php

namespace Drupal\ghi_sections;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectFocusCountryInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Section manager service class.
 */
class SectionManager {

  use DependencySerializationTrait;
  use LayoutEntityHelperTrait;
  use ShortNameTrait;

  /**
   * The machine name of the bundle to use for sections.
   */
  const SECTION_BUNDLES = ['section'];

  /**
   * The route name for the section listing backend page.
   */
  const SECTION_LIST_ROUTE = 'view.content.page_sections';

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a section create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
  }

  /**
   * Get the section node representing the current page.
   *
   * This allows other modules to declare their content as belonging to a
   * section.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to check.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   A section object or NULL.
   */
  public function getCurrentSection($node) {
    $section = NULL;
    if ($node instanceof SectionNodeInterface) {
      $section = $node;
    }
    else {
      // Allow other modules to declare a section as a parent.
      $this->moduleHandler->alter('current_section', $section, $node);
    }
    return $section instanceof SectionNodeInterface ? $section : NULL;
  }

  /**
   * Get the available base object types.
   *
   * @return array
   *   An array of base object types.
   */
  public function getAvailableBaseObjectTypes() {
    // Find out what base objects types can be referenced.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', SectionNodeInterface::BUNDLE);
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
   * @return \Drupal\node\NodeInterface|bool
   *   Either the created section entity, or boolean FALSE.
   */
  public function createSectionForBaseObject(BaseObjectInterface $base_object, array $values) {
    $status = FALSE;
    $base_object_type = $base_object->type->entity;
    if (($base_object_type->needsYearForDataRetrieval() && empty($values['year'])) || empty($values['team'])) {
      return FALSE;
    }
    $tags = $values['tags'] ?? $this->getDefaultTagsFromBaseObject($base_object);
    if (empty($tags)) {
      return FALSE;
    }
    if ($this->loadSectionForBaseObject($base_object)) {
      // There is already a section for this base object.
      return FALSE;
    }
    $section = $this->entityTypeManager->getStorage('node')->create([
      'type' => Section::BUNDLE,
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

    if ($base_object instanceof Plan) {
      if ($shortname = $base_object->getShortName()) {
        $tags[] = $shortname;
      }
      $tags[] = $base_object->getYear();
      $tags[] = $base_object->getPlanTypeShortLabel();
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
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node if one could be found.
   */
  public function loadSectionForBaseObject(BaseObjectInterface $base_object, $year = NULL) {
    if ($base_object->needsYear() && $year === NULL) {
      throw new \InvalidArgumentException(sprintf('Invalid arguments. The base object of type "%s" needs a year, but none has been given.', $base_object->getEntityType()->getLabel()));
    }

    $properties = [
      'type' => SectionNodeInterface::BUNDLE,
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
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of sections assigned to the team.
   */
  public function loadSectionsForTeam(TermInterface $term) {
    $sections = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SectionNodeInterface::BUNDLE,
      'field_team' => $term->id(),
    ]);
    return $sections;
  }

  /**
   * Get related sections for the given section node.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section node for which to retrieve related sections.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes.
   */
  public function getRelatedSections(SectionNodeInterface $section) {
    $base_object = $section->getBaseObject();
    $sections = [];
    if ($section->hasField('field_year') && !$section->get('field_year')->isEmpty()) {
      // This is either a global section page or a section page with a base
      // object that needs an additional year specified.
      $args = array_filter([
        'type' => $section->bundle(),
        'field_base_object' => $base_object?->id(),
      ]);
      $candidates = $this->entityTypeManager->getStorage($section->getEntityTypeId())->loadByProperties($args);
      foreach ($candidates as $candidate) {
        $year = $candidate->get('field_year')->value;
        $sections[$year] = $candidate;
      }
    }
    elseif ($base_object && $base_object->hasField('field_focus_country')) {
      // This is a section page with no year but with a focus country field,
      // e.g. a plan based section page.
      $sections = $this->getSectionsByBaseObjectFocusCountry($section);
    }
    elseif ($base_object) {
      // This is a section page with no year, e.g. a plan based section page.
      $sections = $this->getSectionsByBaseObjectCountryReference($section);
    }

    if (empty($sections)) {
      return NULL;
    }

    return $sections;
  }

  /**
   * Get related sections by a focus country on the sections base object.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section node for which to retrieve related sections.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id
   */
  private function getSectionsByBaseObjectFocusCountry(SectionNodeInterface $section) {
    $options = [];
    $base_object = $section->getBaseObject();
    if (!$base_object instanceof BaseObjectFocusCountryInterface || !$base_object->getFocusCountry()) {
      return $options;
    }
    $focus_country = $base_object->getFocusCountry();

    // Find other object candidates that have the same focus country.
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_object_candidates */
    $base_object_candidates = $this->entityTypeManager->getStorage($base_object->getEntityTypeId())->loadByProperties([
      'type' => $base_object->bundle(),
      'field_focus_country' => $focus_country->id(),
    ]);

    // If base object is a plan, thus looking for other plan base objects,
    // apply filtering based on the plan type.
    if ($base_object instanceof Plan) {
      $base_object_candidates = array_filter($base_object_candidates, function (Plan $base_object_candidate) use ($base_object) {
        // If the current base object is of type RRP, we want to retain only
        // candidates that are also RRPs. If it's not an RRP, we only want
        // other candiates that are not RRPs either.
        return $base_object->isRrp() ? $base_object_candidate->isRrp() : !$base_object_candidate->isRrp();
      });
    }

    return $this->getSectionOptionsForBaseObjects($section, $base_object_candidates);
  }

  /**
   * Get related sections by a country reference on the sections base object.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section node for which to retrieve related sections.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id
   */
  private function getSectionsByBaseObjectCountryReference(SectionNodeInterface $section) {
    $options = [];
    $base_object = $section->getBaseObject();
    if (!$base_object || !$base_object->hasField('field_country') || $base_object->get('field_country')->isEmpty()) {
      return $options;
    }

    // Get the list of all countries associated with this object.
    $country_ids = array_map(function ($country) {
      return $country->id();
    }, $base_object->get('field_country')->referencedEntities());

    // Find other object candidates that have at least one of these countries
    // associated.
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_object_candidates */
    $base_object_candidates = $this->entityTypeManager->getStorage($base_object->getEntityTypeId())->loadByProperties([
      'type' => $base_object->bundle(),
      'field_country' => $country_ids,
    ]);

    // Then filter out the ones that don't share the full set of countries.
    $base_object_candidates = array_filter($base_object_candidates, function ($base_object_candidate) use ($country_ids) {
      $candidate_country_ids = array_map(function ($country) {
        return $country->id();
      }, $base_object_candidate->get('field_country')->referencedEntities());
      return empty(array_diff($country_ids, $candidate_country_ids)) && count($candidate_country_ids) == count($country_ids);
    });
    if (empty($base_object_candidates)) {
      return $options;
    }
    return $this->getSectionOptionsForBaseObjects($section, $base_object_candidates);
  }

  /**
   * Get the section options for the given base object.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The current section node.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_objects
   *   The base objects.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id.
   */
  private function getSectionOptionsForBaseObjects(SectionNodeInterface $section, array $base_objects) {
    $base_object = $section->getBaseObject();
    // Then load the sections associated to these objects.
    /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface[] $section_candidates */
    $section_candidates = $this->entityTypeManager->getStorage($section->getEntityTypeId())->loadByProperties([
      'type' => $section->bundle(),
      'field_base_object' => array_keys($base_objects),
    ]);
    foreach ($section_candidates as $section_candidate) {
      if (!$section_candidate->access('view')) {
        continue;
      }
      $options[$section_candidate->getBaseObject()->getSourceId()] = $section_candidate;
    }

    // Sort the options.
    if ($base_object->hasField('field_year')) {
      // If the base object has a year field, use that for sorting.
      usort($options, function ($section_a, $section_b) {
        $year_a = $section_a->getBaseObject()->get('field_year')->value;
        $year_b = $section_b->getBaseObject()->get('field_year')->value;
        return $year_a - $year_b;
      });
    }
    else {
      // Otherwise just use the base objects original id as a best guess.
      ksort($options);
    }
    return array_reverse($options);
  }

  /**
   * Set the module handler service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

}
