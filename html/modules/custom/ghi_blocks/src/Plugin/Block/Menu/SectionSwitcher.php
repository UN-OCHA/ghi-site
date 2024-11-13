<?php

namespace Drupal\ghi_blocks\Plugin\Block\Menu;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\Traits\SectionPathTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SectionSwitcher' block.
 *
 * @Block(
 *  id = "section_switcher",
 *  admin_label = @Translation("Section switcher"),
 *  category = @Translation("Menus"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class SectionSwitcher extends BlockBase implements ContainerFactoryPluginInterface {

  use SectionPathTrait;
  use ShortNameTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Menu\SectionSwitcher $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    $instance->subpageManager = $container->get('ghi_subpages.manager');
    $instance->aliasManager = $container->get('path_alias.manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $section = $this->getSectionNode();
    $sections = $this->buildSectionSwitcherOptions();
    if (!$sections) {
      return [];
    }
    $build = [
      '#theme' => 'section_switcher',
      '#title' => $this->t('Other years'),
      '#sections' => $sections,
      '#current_section' => $section,
    ];
    return $build;
  }

  /**
   * Build the section switcher options.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes to be used as options.
   */
  private function buildSectionSwitcherOptions() {

    $section_node = $this->getSectionNode();
    if (!$section_node) {
      return NULL;
    }

    $base_object = $section_node->getBaseObject();
    $sections = [];
    if (!$section_node->get('field_year')->isEmpty()) {
      // This is either a global section page or a section page with a base
      // object that needs an additional year specified.
      $args = array_filter([
        'type' => $section_node->bundle(),
        'field_base_object' => $base_object?->id(),
      ]);
      $candidates = $this->entityTypeManager->getStorage($section_node->getEntityTypeId())->loadByProperties($args);
      foreach ($candidates as $candidate) {
        $year = $candidate->get('field_year')->value;
        $sections[$year] = $candidate;
      }
    }
    elseif ($base_object && $base_object->hasField('field_focus_country')) {
      // This is a section page with no year but with a focus country field,
      // e.g. a plan based section page.
      $sections = $this->getSectionsByBaseObjectFocusCountry();
    }
    elseif ($base_object) {
      // This is a section page with no year, e.g. a plan based section page.
      $sections = $this->getSectionsByBaseObjectCountryReference();
    }

    if (empty($sections)) {
      return NULL;
    }

    return $sections;
  }

  /**
   * Get the current section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The current section node or NULL if none can be found.
   */
  private function getSectionNode() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->hasContextValue()) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $contexts['node']->getContextValue();
    $section_node = $this->subpageManager->getBaseTypeNode($node);
    while (!$section_node instanceof SectionNodeInterface && $section_node !== NULL) {
      $section_node = $this->subpageManager->getBaseTypeNode($section_node);
    }
    if (!$section_node instanceof SectionNodeInterface) {
      // We can still try to deduce the section from the path.
      $section_node = $this->getSectionNodeFromPath();
    }
    return $section_node instanceof SectionNodeInterface ? $section_node : NULL;
  }

  /**
   * Get switcher options by a country reference on the sections base objects.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id
   */
  private function getSectionsByBaseObjectFocusCountry() {
    $options = [];
    $section_node = $this->getSectionNode();
    $base_object = $section_node->getBaseObject();
    if (!$base_object || !$base_object->hasField('field_focus_country') || $base_object->get('field_focus_country')->isEmpty()) {
      return $options;
    }
    $focus_country = $base_object->get('field_focus_country')->entity;

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

    return $this->getSectionOptionsForBaseObjects($section_node, $base_object_candidates);
  }

  /**
   * Get switcher options by a country reference on the sections base objects.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id
   */
  private function getSectionsByBaseObjectCountryReference() {
    $options = [];
    $section_node = $this->getSectionNode();
    $base_object = $section_node->getBaseObject();
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
    return $this->getSectionOptionsForBaseObjects($section_node, $base_object_candidates);
  }

  /**
   * Get the section options for the given base object.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The current section node.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_objects
   *   The base objects.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes keyed by the base object original id.
   */
  private function getSectionOptionsForBaseObjects(SectionNodeInterface $section_node, array $base_objects) {
    $base_object = $section_node->getBaseObject();
    // Then load the sections associated to these objects.
    /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface[] $section_candidates */
    $section_candidates = $this->entityTypeManager->getStorage($section_node->getEntityTypeId())->loadByProperties([
      'type' => $section_node->bundle(),
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

}
