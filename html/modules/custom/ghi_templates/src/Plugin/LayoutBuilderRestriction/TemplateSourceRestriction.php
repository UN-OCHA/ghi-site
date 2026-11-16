<?php

namespace Drupal\ghi_templates\Plugin\LayoutBuilderRestriction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Attribute\LayoutBuilderRestriction;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses the origin node's restrictions when adding elements to a template.
 *
 * Existing copied components remain editable and movable even if their plugin
 * is no longer allowed for new additions on the origin node.
 */
#[LayoutBuilderRestriction(
  id: 'ghi_template_source',
  title: new TranslatableMarkup('Page template source'),
  description: new TranslatableMarkup('Allow new template elements according to the origin node.')
)]
class TemplateSourceRestriction extends LayoutBuilderRestrictionBase {

  use LayoutEntityHelperTrait;

  /**
   * Constructs the source restriction.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions $allowedBlockDefinitions
   *   The configured block restriction lookup.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected AllowedBlockDefinitions $allowedBlockDefinitions, SectionStorageManagerInterface $section_storage_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sectionStorageManager = $section_storage_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('class_resolver')->getInstanceFromDefinition(AllowedBlockDefinitions::class), $container->get('plugin.manager.layout_builder.section_storage'));
  }

  /**
   * {@inheritdoc}
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    $section_storage = $context['section_storage'] ?? NULL;
    $template = $section_storage instanceof SectionStorageInterface ? $this->getTemplate($section_storage) : NULL;
    if (!$template) {
      return $definitions;
    }
    return array_intersect_key($definitions, $this->getSourceDefinitions($template));
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    $template = $this->getTemplate($section_storage);
    if (!$template) {
      return parent::inlineBlocksAllowedinContext($section_storage, $delta, $region);
    }
    return array_values(array_intersect($this->getInlineBlockPlugins(), array_keys($this->getSourceDefinitions($template))));
  }

  /**
   * Gets an actual template instance, not its shared display configuration.
   */
  private function getTemplate(SectionStorageInterface $section_storage): ?PageTemplateInterface {
    $context = $section_storage->getContexts()['entity'] ?? NULL;
    if (!$context || !$context->hasContextValue()) {
      return NULL;
    }
    $entity = $context->getContextValue();
    return $entity instanceof PageTemplateInterface ? $entity : NULL;
  }

  /**
   * Gets the current source policy, failing closed without a usable origin.
   */
  private function getSourceDefinitions(PageTemplateInterface $template): array {
    $source = $template->getSourceEntity();
    if (!$source instanceof NodeInterface || !$section_storage = $this->getSectionStorageForEntity($source)) {
      return [];
    }
    // The source is a node, so this plugin is a no-op in the nested pipeline.
    // Reuse all enabled restrictions without restarting template filtering.
    return $this->allowedBlockDefinitions->getDefinitions($section_storage);
  }

}
