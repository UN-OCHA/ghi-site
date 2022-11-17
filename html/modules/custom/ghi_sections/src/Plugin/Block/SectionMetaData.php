<?php

namespace Drupal\ghi_sections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_sections\Entity\GlobalSection;
use Drupal\ghi_sections\Entity\Homepage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SectionMetaData' block.
 *
 * @Block(
 *  id = "section_meta_data",
 *  admin_label = @Translation("Section meta data"),
 *  category = @Translation("Page"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class SectionMetaData extends BlockBase implements ContainerFactoryPluginInterface {

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
    $instance->subpageManager = $container->get('ghi_subpages.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $section = $this->getSectionNode();
    if (!$section) {
      return NULL;
    }
    return [
      '#markup' => $section->getPageTitleMetaData(),
      '#full_width' => TRUE,
    ];
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
    // Let's not handle global sections that are not homepages for the moment.
    if ($section_node instanceof GlobalSection && !$section_node instanceof Homepage) {
      return NULL;
    }
    return $section_node;
  }

}
