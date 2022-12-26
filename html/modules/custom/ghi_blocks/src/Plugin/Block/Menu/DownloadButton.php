<?php

namespace Drupal\ghi_blocks\Plugin\Block\Menu;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\hpc_downloads\EntityPageDownloadPlugin;
use Drupal\page_manager\Entity\PageVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'DownloadButton' block.
 *
 * @Block(
 *  id = "download_button",
 *  admin_label = @Translation("Download Button"),
 *  category = @Translation("Menus")
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
    $download_plugin = $this->getDownloadPlugin();
    if (!$download_plugin) {
      return;
    }
    $build = $this->downloadDialog->buildDialogLink($download_plugin, [
      [
        '#markup' => Markup::create('<svg class="cd-icon ghi-icon--pdf" aria-hidden="true" focusable="false" width="16" height="16"><use xlink:href="#ghi-icon--pdf"></use></svg>'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Download page'),
      ],
      '#cache' => [
        'tags' => $download_plugin->getCacheTags(),
        'contexts' => $download_plugin->getCacheContexts(),
      ],
    ]);

    return $build;
  }

  /**
   * Get the download plugin for the current page.
   *
   * @return \Drupal\hpc_downloads\EntityPageDownloadPlugin
   *   The download plugin.
   */
  private function getDownloadPlugin() {
    $entity = $this->routeMatch->getParameter('node') ?? NULL;
    if ($entity) {
      return new EntityPageDownloadPlugin($entity, $this->requestStack);
    }
    $entity = $this->requestStack->getCurrentRequest()->attributes->get('_entity') ?? NULL;
    if ($entity instanceof PageVariant) {
      return new EntityPageDownloadPlugin($entity, $this->requestStack);
    }
  }

}
