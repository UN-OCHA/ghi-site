<?php

namespace Drupal\ghi_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\Plugin\Field\FieldType\RemoteItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ghi_remote_article' formatter.
 */
abstract class RemoteContentFormatterSourceLinkBase extends RemoteContentFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  public $redirectDestination;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->redirectDestination = $container->get('redirect.destination');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_label' => '',
      'link_to_edit' => FALSE,
      'include_publisher_destination' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['link_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link label'),
      '#default_value' => $this->getSetting('link_label'),
      '#description' => $this->t('The link label can be specified. If left empty, <em>Source</em> will be used.'),
    ];
    $elements['link_to_edit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to edit page'),
      '#default_value' => $this->getSetting('link_to_edit'),
      '#description' => $this->t('Whether the link should go to the edit page instead of a public view page.'),
    ];
    $elements['include_publisher_destination'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include publisher destination'),
      '#default_value' => $this->getSetting('include_publisher_destination'),
      '#description' => $this->t('If checked the link will include query arguments that allow to user to be returned to the current page on this site after doing any action on the remote site. Depends on support for this feature in the remote system.'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $link_label = $this->getSetting('link_label');
    $element = [];
    foreach ($items as $delta => $item) {
      if (!$item instanceof RemoteItemBase) {
        continue;
      }
      $remote_source = $item->remote_source;
      $content_id = $item->{$item->getIdColumnName()};
      /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
      $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
      $remote_url = $remote_source_instance->getContentUrl($content_id, !$this->getSetting('link_to_edit') ? 'canonical' : 'edit');
      if ($this->getSetting('include_publisher_destination')) {
        $remote_url->setOption('query', [
          'publisher' => 'ghi',
          'publisher_destination' => Url::fromUserInput($this->redirectDestination->get())->setAbsolute()->toString(),
        ]);
      }
      $element[$delta] = Link::fromTextAndUrl(!empty($link_label) ? $link_label : $this->t('Source'), $remote_url)->toRenderable();
    }
    return $element;
  }

}
