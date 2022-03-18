<?php

namespace Drupal\ghi_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'ghi_remote_article' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_remote_article_source_link",
 *   label = @Translation("Source link"),
 *   field_types = {"ghi_remote_article"}
 * )
 */
class ArticleFormatterSourceLink extends ArticleFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_label' => '',
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
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $link_label = $this->getSetting('link_label');
    $element = [];
    foreach ($items as $delta => $item) {
      $remote_source = $item->remote_source;
      $article_id = $item->article_id;
      /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
      $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
      $remote_url = $remote_source_instance->getContentUrl($article_id);
      $element[$delta] = Link::fromTextAndUrl(!empty($link_label) ? $link_label : $this->t('Source'), $remote_url)->toRenderable();
    }
    return $element;
  }

}
