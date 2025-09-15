<?php

namespace Drupal\ghi_geojson\Plugin\Menu\LocalAction;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a local action plugin with a dynamic title.
 */
class DeleteLocalAction extends LocalActionDefault {

  /**
   * The layout builder modal config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $modalConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->modalConfig = $container->get('config.factory')->get('layout_builder_modal.settings');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = (array) $this->pluginDefinition['options'];
    $options['attributes'] = [
      'class' => [
        'use-ajax',
        'delete-local-action',
      ],
      'data-dialog-type' => 'dialog',
      'data-dialog-options' => Json::encode([
        'width' => $this->modalConfig->get('modal_width'),
        'height' => $this->modalConfig->get('modal_height'),
        'target' => 'layout-builder-modal',
        'autoResize' => $this->modalConfig->get('modal_autoresize'),
        'modal' => TRUE,
      ]),
    ];
    return $options;
  }

}