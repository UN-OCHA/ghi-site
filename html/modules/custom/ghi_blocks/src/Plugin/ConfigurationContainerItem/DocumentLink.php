<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  public $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

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
      '#unique_filetype' => FALSE,
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

  /**
   * Get the date for a document link.
   *
   * @return string
   *   The formatted date of the document link.
   */
  public function getFormattedDate() {
    $document = $this->config['value'];
    $timestamp = strtotime($document['date']);
    $date = $this->dateFormatter->format($timestamp, 'custom', 'd M Y');
    return $date;
  }

  /**
   * Get the configured languages for a document link.
   *
   * @return string
   *   The configured languages for a document link, separated by comma.
   */
  public function getConfiguredLanguages() {
    $document = $this->config['value'];
    $languages = array_keys(array_filter($document['file_details'], function ($details) {
      return !empty($details['target_url']);
    }));
    return implode(', ', $languages);
  }

}
