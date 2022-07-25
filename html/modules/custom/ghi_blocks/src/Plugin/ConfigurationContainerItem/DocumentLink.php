<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;

/**
 * Provides a document link item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "document_link",
 *   label = @Translation("Document link"),
 *   description = @Translation("This item displays a document link, supporting multiple languages."),
 * )
 */
class DocumentLink extends ConfigurationContainerItemPluginBase {

  const TITLE_MAX_LENGTH = 50;

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label'] = [
      '#title' => $this->t('Title'),
      '#description' => $this->t('A title for this document. If the title is longer than @max_length characters, it will be truncated.', [
        '@max_length' => self::TITLE_MAX_LENGTH,
      ]),
    ] + $element['label'];

    $element['value'] = [
      '#type' => 'document_link',
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $document = $this->config['value'];
    $document['title'] = $this->config['label'];
    return [
      '#theme' => 'document_link_box',
      '#document' => $document,
    ];
  }

}
