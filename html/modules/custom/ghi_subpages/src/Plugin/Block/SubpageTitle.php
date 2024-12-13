<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageIconInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
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

    $subpage_manager = SubpageHelper::getSubpageManager();
    $title = $node->type->entity->getThirdPartySetting('ghi_subpages', 'page_title') ?: $node->getTitle();
    if ($node instanceof SectionNodeInterface && $subpage_manager->getSectionOverviewLabel($node)) {
      $title = $subpage_manager->getSectionOverviewLabel($node);
    }

    $build = [
      '#full_width' => TRUE,
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => $node->getCacheContexts(),
      ],
      '#attributes' => [
        'class' => ['subpage-title-block'],
      ],
      'title' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'subpage-title-wrapper',
            'content-width',
          ],
        ],
      ],
    ];

    // If we have a parent context, we also want to add a breadcrumb.
    $parent_node = $node instanceof SubpageNodeInterface ? $node->getParentNode() : NULL;
    if ($parent_node && !$parent_node instanceof SectionNodeInterface) {
      $title_prefix = new FormattableMarkup('<span class="document-link">@parent</span>', [
        '@parent' => $parent_node->toLink()->toString(),
      ]);
      $build['title'][] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $title_prefix,
      ];
      $build['title']['#attributes']['class'][] = 'has-title-prefix';
    }

    $title_tag = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title,
    ];
    if ($node instanceof SubpageIconInterface) {
      $build['title'][] = [
        'icon' => $node->getIcon(),
        'title' => $title_tag,
      ];
      $build['title']['#attributes']['class'][] = 'has-icon';
    }
    else {
      $build['title'][] = $title_tag;
    }

    return $build;
  }

}
