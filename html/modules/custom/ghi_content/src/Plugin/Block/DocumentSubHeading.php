<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'DocumentSubHeading' block.
 *
 * @Block(
 *  id = "document_subheading",
 *  admin_label = @Translation("Subheading"),
 *  category = @Translation("Narrative Content"),
 *  title = FALSE,
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class DocumentSubHeading extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $subheading = $this->getSubheading();
    if (!$subheading) {
      return;
    }

    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#attributes' => [
          'name' => $this->getSubheadingId(),
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => Markup::create($subheading),
      ],
    ];
  }

  /**
   * Get the configured subheading.
   *
   * @return string
   *   The configured subheading.
   */
  public function getSubheading() {
    $conf = $this->getBlockConfig();
    return !empty($conf['value']) ? Html::escape($conf['value']) : NULL;
  }

  /**
   * Get the id for this subheading.
   *
   * To be used in anchor links.
   *
   * @return string
   *   The subheading id.
   */
  public function getSubheadingId() {
    $ids = &drupal_static(__FUNCTION__, []);
    $subheading = $this->getSubheading();
    if (!array_key_exists($subheading, $ids)) {
      $ids[$subheading] = $subheading ? Html::getUniqueId($subheading) : '';
    }
    return $ids[$subheading];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm($form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subheading'),
      '#default_value' => $this->getBlockConfig()['value'] ?? NULL,
      '#description' => $this->t('Enter a string to be used as a subheading for the current document. This field does not support HTML tags.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
