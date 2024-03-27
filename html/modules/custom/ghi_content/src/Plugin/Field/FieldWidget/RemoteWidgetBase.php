<?php

namespace Drupal\ghi_content\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract base class for remote content field widgets.
 */
abstract class RemoteWidgetBase extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The attachment query.
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $remote_sources = $this->remoteSourceManager->getDefinitions();

    /** @var \Drupal\ghi_content\Plugin\Field\FieldType\RemoteItemBase $item */
    $item = $items[$delta];

    $remote_source = $items[$delta]->remote_source ?? array_key_first($remote_sources);
    $content_type = $item->getContentType();
    $content_id = $item->getContentId();

    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceBase $remote_source_instance */
    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    $content = $remote_source_instance->getContent($content_type, $content_id);

    $element['content'] = [
      '#type' => 'remote_' . $content_type,
      '#default_value' => $content ? $content : NULL,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $remote_sources = $this->remoteSourceManager->getDefinitions();
    foreach ($values as $delta => $value) {
      if (empty($value['content']['remote_source'])) {
        $value['content']['remote_source'] = array_key_first($remote_sources);
      }
      $values[$delta] = $value['content'];
    }
    return $values;
  }

}
