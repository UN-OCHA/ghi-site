<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;

/**
 * Provides an attachment label item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "attachment_label",
 *   label = @Translation("Attachment label"),
 *   description = @Translation("This item displays the label of an attachment."),
 * )
 */
class AttachmentLabel extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['id_prefix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prefix with ID'),
    ];
    $state_selector = FormElementHelper::getStateSelector($element, ['id_prefix']);
    $element['id_type'] = [
      '#type' => 'select',
      '#title' => $this->t('ID type'),
      '#options' => AttachmentHelper::idTypes(),
      '#states' => [
        'visible' => [
          'input[name="' . $state_selector . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    $configuration = $this->getPluginConfiguration();
    return $configuration['default_label'] ?? $this->t('Attachment');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getContextValue('attachment');
    if (!$attachment || !$attachment instanceof AttachmentInterface) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment */
    $prefix = NULL;
    if ($this->get('id_prefix')) {
      $prefix = AttachmentHelper::getCustomAttachmentId($attachment, $this->get('id_type'));
    }
    return $prefix ? ($prefix . ': ' . $attachment->getDescription()) : $attachment->getDescription();
  }

}
