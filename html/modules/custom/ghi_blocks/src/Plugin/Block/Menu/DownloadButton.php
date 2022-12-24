<?php

namespace Drupal\ghi_blocks\Plugin\Block\Menu;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\hpc_downloads\NodeDownloadPlugin;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'DownloadButton' block.
 *
 * @Block(
 *  id = "download_button",
 *  admin_label = @Translation("Download Button"),
 *  category = @Translation("Menus"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class DownloadButton extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The download dialog.
   *
   * @var \Drupal\hpc_downloads\DownloadDialog\DownloadDialogPlugin
   */
  protected $downloadDialog;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Menu\DownloadButton $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->requestStack = $container->get('request_stack');
    $instance->downloadDialog = $container->get('hpc_downloads.download_dialog_plugin');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node') ?? NULL;
    if (!$node) {
      return NULL;
    }
    $node_download_plugin = new NodeDownloadPlugin($node, $this->requestStack);
    $build = $this->downloadDialog->buildDialogLink($node_download_plugin, [
      [
        '#markup' => Markup::create('<svg class="cd-icon ghi-icon--pdf" aria-hidden="true" focusable="false" width="16" height="16"><use xlink:href="#ghi-icon--pdf"></use></svg>'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Download page'),
      ],
    ]);

    return $build;
  }

}
