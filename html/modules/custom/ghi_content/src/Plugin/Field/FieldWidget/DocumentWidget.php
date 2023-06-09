<?php

namespace Drupal\ghi_content\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'ghi_remote_document' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_remote_document",
 *   label = @Translation("Remote document"),
 *   field_types = {"ghi_remote_document"},
 * )
 */
class DocumentWidget extends WidgetBase implements ContainerFactoryPluginInterface {

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

    $remote_source = $items[$delta]->remote_source ?? array_key_first($remote_sources);
    $document_id = $items[$delta]->document_id ?? NULL;

    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    $document = $remote_source_instance->getDocument($document_id);

    $element['document'] = [
      '#type' => 'remote_document',
      '#default_value' => $document ? $document : NULL,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $remote_sources = $this->remoteSourceManager->getDefinitions();
    foreach ($values as $delta => $value) {
      if (empty($value['document']['remote_source'])) {
        $value['document']['remote_source'] = array_key_first($remote_sources);
      }
      $values[$delta] = $value['document'];
    }
    return $values;
  }

}
