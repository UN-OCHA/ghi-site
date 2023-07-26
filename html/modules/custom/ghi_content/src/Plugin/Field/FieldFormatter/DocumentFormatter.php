<?php

namespace Drupal\ghi_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ghi_remote_document' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_remote_document",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_remote_document"}
 * )
 */
class DocumentFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  public $remoteSourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->remoteSourceManager = $container->get('plugin.manager.remote_source');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    return $element;
  }

}
