<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;

/**
 * Provides a label/value item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "label_value",
 *   label = @Translation("Label/value"),
 *   description = @Translation("This item displays an arbitrary label/value pair."),
 * )
 */
class LabelValue extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];
    $formatting_options = DataAttachment::getFormattingOptions();
    unset($formatting_options['auto']);
    $element['formatting'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatting'),
      '#options' => $formatting_options,
      '#default_value' => array_key_exists('formatting', $this->config) ? $this->config['formatting'] : NULL,
    ];

    if (!empty($this->getPluginConfiguration()['footnote'])) {
      $element['footnote'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Footnote'),
        '#default_value' => array_key_exists('footnote', $this->config) ? $this->config['footnote'] : NULL,
        '#maxlength' => 255,
      ];
    }

    if (!empty($this->getPluginConfiguration()['custom_logo'])) {
      $element['custom_logo'] = [
        '#type' => 'select',
        '#title' => $this->t('Logo'),
        '#options' => [
          'none' => $this->t('None'),
        ] + $this->customLogosOptions(),
        '#default_value' => array_key_exists('custom_logo', $this->config) ? $this->config['custom_logo'] : NULL,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $value = $this->getValue();
    $formatting = $this->config['formatting'] ?? 'raw';
    $decimal_format = NULL;
    switch ($formatting) {
      case 'raw':
        $rendered_value = [
          '#markup' => $value,
        ];
        break;

      case 'currency':
        $rendered_value = [
          '#theme' => 'hpc_currency',
          '#value' => $value,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount_rounded':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'percent':
        $rendered_value = [
          '#theme' => 'hpc_percent',
          '#ratio' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;
    }

    $icons = [];
    $footnote = $this->config['footnote'] ?? NULL;
    if ($footnote) {
      $icons[] = [
        '#theme' => 'hpc_tooltip',
        '#tooltip' => [
          '#plain_text' => $footnote,
        ],
      ];
    }

    $custom_logo = $this->config['custom_logo'] ?? NULL;
    if ($custom_logo && array_key_exists($custom_logo, $this->customLogos())) {
      $logo = $this->customLogos()[$custom_logo];
      $path_resolver = \Drupal::service('extension.path.resolver');
      $icon = $logo['icon'] ?? ($custom_logo . '.svg');
      $icon_path = $path_resolver->getPath('module', 'ghi_blocks') . '/assets/custom_logos/' . $icon;
      if (file_exists($icon_path)) {
        $icons[] = [
          '#type' => 'link',
          '#title' => [
            '#theme' => 'image',
            '#uri' => '/' . $icon_path,
            '#attributes' => [
              'class' => 'custom-logo custom-logo--' . $custom_logo,
              'title' => $logo['label'],
            ],
            '#alt' => $logo['label'],
          ],
          '#url' => $logo['url'],
          '#attributes' => [
            'target' => '_blank',
            'title' => $logo['label'],
            'class' => [
              'custom-link',
              'custom-link--' . $custom_logo,
            ],
          ],
        ];
      }
    }

    if (!empty($icons)) {
      $rendered_value = [
        '#type' => 'container',
        0 => $rendered_value,
        'tooltips' => [
          '#theme' => 'hpc_tooltip_wrapper',
          '#tooltips' => $icons,
        ],
      ];
    }
    return $rendered_value;
  }

  /**
   * Get the options for available custom logos.
   *
   * @return array
   *   An array of key value pairs for the available custom logos.
   */
  private function customLogosOptions() {
    $logos = $this->customLogos();
    return array_map(function ($item) {
      return $item['label'];
    }, $logos);
  }

  /**
   * Return the defined set of available custom logos.
   *
   * @return array
   *   An array describing the set of available custom logos.
   *   Supported keys for each item are:
   *   - label: The label to be used for the title and alt attributes.
   *   - url: A url object used to create the target link.
   *   - icon (optional): The name of the icon file under
   *     /modules/ghi_blocks/assets/custom_logos. If no icon is provided, the
   *     name will be build using the array key and the suffix ".svg".
   */
  private function customLogos() {
    return [
      'rft' => [
        'label' => $this->t('Refugee Funding Tracker (RFT)'),
        'url' => Url::fromUri('https://refugee-funding-tracker.org'),
      ],
    ];
  }

}
