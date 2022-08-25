<?php

namespace Drupal\ghi_blocks\Plugin\Block\Menu;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\ghi_sections\Entity\GlobalSection;
use Drupal\ghi_sections\Entity\Homepage;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_subpages\Plugin\Block\SubpageNavigation $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    $instance->subpageManager = $container->get('ghi_subpages.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $sections = $this->buildSectionSwitcherOptions();
    if (!$sections) {
      return [];
    }
    $build = [
      '#theme' => 'section_switcher',
      '#title' => $this->t('Other years'),
      '#sections' => $sections,
      '#current_section' => $this->getSectionNode(),
    ];
    return $build;
  }

  /**
   * Build the section switcher options.
   *
   * @return \Drupal\node\NodeInterface
   *   An array of section nodes to be used as options.
   */
  private function buildSectionSwitcherOptions() {

    $section_node = $this->getSectionNode();
    if (!$section_node) {
      return NULL;
    }

    $sections = [];
    if (!$section_node->get('field_year')->isEmpty()) {
      // This is either a global section page or a section page with a base
      // object that needs an additional year specified.
      $args = [
        'type' => $section_node->bundle(),
      ];
      if ($section_node->hasField('field_base_object')) {
        $args['field_base_object'] = $section_node->get('field_base_object')->entity->id();
      }
      $candidates = $this->entityTypeManager->getStorage($section_node->getEntityTypeId())->loadByProperties($args);
      foreach ($candidates as $candidate) {
        $year = $candidate->get('field_year')->value;
        $sections[$year] = $candidate;
      }
    }
    else {
      // This is a section page with no year, e.g. a plan based section page.
      $sections = $this->getSectionsByBaseObjectCountryReference();
    }

    if (count($sections) <= 1) {
      return NULL;
    }

    return $sections;
  }

  /**
   * Get the current section node.
   *
   * @return \Drupal\node\NodeInterface|null
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
    // Let's not handle global sections that are not homepages for the moment.
    if ($section_node instanceof GlobalSection && !$section_node instanceof Homepage) {
      return NULL;
    }
    return $section_node;
  }

  /**
   * Get switcher options by a country reference on the sections base objects.
   *
   * @return array
   *   An array of either markup or link render array items.
   */
  private function getSectionsByBaseObjectCountryReference() {
    $options = [];
    $section_node = $this->getSectionNode();
    $base_object = $section_node->get('field_base_object')->entity ?? NULL;
    if (!$base_object || !$base_object->hasField('field_country') || $base_object->get('field_country')->isEmpty()) {
      return $options;
    }
    $country = $base_object->get('field_country')->entity;
    $base_object_candidates = $this->entityTypeManager->getStorage($base_object->getEntityTypeId())->loadByProperties([
      'type' => $base_object->bundle(),
      'field_country' => $country->id(),
    ]);
    $section_candidates = $this->entityTypeManager->getStorage($section_node->getEntityTypeId())->loadByProperties([
      'type' => $section_node->bundle(),
      'field_base_object' => array_keys($base_object_candidates),
    ]);
    foreach ($section_candidates as $section_candidate) {
      if (!$section_candidate->access('view')) {
        continue;
      }
      $options[$section_candidate->get('field_base_object')->entity->id()] = $section_candidate;
    }
    ksort($options);
    return array_reverse($options);
  }

}
