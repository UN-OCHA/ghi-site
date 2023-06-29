<?php

namespace Drupal\ghi_sections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_sections\Plugin\Block\SectionMetaData $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->sectionManager = $container->get('ghi_sections.manager');
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
    $metadata = $section->getPageTitleMetaData();
    if (!$metadata) {
      return NULL;
    }
    if ($this->getPageNode()?->isPublished()) {
      $metadata[] = [
        '#theme' => 'social_links',
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $metadata,
      '#full_width' => TRUE,
      '#cache' => [
        'contexts' => ['url.path'],
      ],
    ];
  }

  /**
   * Get the current section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The current section node or NULL if none can be found.
   */
  private function getSectionNode() {
    $node = $this->getPageNode();
    $section_node = $this->sectionManager->getCurrentSection($node);
    return $section_node;
  }

  /**
   * Get the current page node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The current page node or NULL if none can be found.
   */
  private function getPageNode() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->hasContextValue()) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface $node */
    return $contexts['node']->getContextValue();
  }

}
