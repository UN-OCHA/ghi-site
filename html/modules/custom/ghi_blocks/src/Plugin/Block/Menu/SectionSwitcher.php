<?php

namespace Drupal\ghi_blocks\Plugin\Block\Menu;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
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
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]|null
   *   An array of section nodes to be used as options or NULL.
   */
  private function buildSectionSwitcherOptions() {
    $section_node = $this->getSectionNode();
    if (!$section_node) {
      return NULL;
    }
    return $this->sectionManager->getRelatedSections($section_node) ?: NULL;
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

}
