<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a 'Document link button' block.
 */
#[Block(
  id: 'generic_document_link_button',
  admin_label: new TranslatableMarkup('Document link button'),
  category: new TranslatableMarkup('Generic elements'),
)]
class DocumentLinkButton extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(usesTitle: FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();
    if (empty($conf['document'])) {
      return NULL;
    }

    return [
      '#theme' => 'document_link_button',
      '#button_label' => $conf['button_label'] ?: $this->t('Download report'),
      '#document' => $conf['document'],
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'button_label' => NULL,
      'document' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the download button'),
      '#description' => $this->t('You can set a custom label for the download button. Leave empty to use the default title <em>Download report</em>'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'button_label'),
    ];
    $form['document'] = [
      '#type' => 'document_link',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'links'),
      '#date' => FALSE,
    ];
    return $form;
  }

}
