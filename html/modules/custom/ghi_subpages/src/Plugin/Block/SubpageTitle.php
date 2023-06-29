<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SubpageTitle' block.
 *
 * @Block(
 *  id = "subpage_title",
 *  admin_label = @Translation("Subpage title"),
 *  category = @Translation("Page"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class SubpageTitle extends BlockBase implements ContainerFactoryPluginInterface {

  use SubpageTrait;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_subpages\Plugin\Block\SubpageTitle $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return NULL;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $contexts['node']->getContextValue();
    if (!$node || !$node instanceof NodeInterface) {
      return NULL;
    }

    // Get the section.
    $section = $this->sectionManager->getCurrentSection($node);
    if (!$section) {
      // Don't show the subpage title if no parent section is available.
      return NULL;
    }

    $title = $node->getTitle();
    if (SubpageHelper::isBaseTypeNode($node) && SubpageHelper::getSectionOverviewLabel($node)) {
      $title = SubpageHelper::getSectionOverviewLabel($node);
    }
    if ($title) {
      return [
        '#markup' => new FormattableMarkup('<h2 class="content-width">@title</h2>', [
          '@title' => $title,
        ]),
        '#full_width' => TRUE,
        '#cache' => [
          'tags' => $node->getCacheTags(),
          'contexts' => $node->getCacheContexts(),
        ],
      ];
    }
  }

}
