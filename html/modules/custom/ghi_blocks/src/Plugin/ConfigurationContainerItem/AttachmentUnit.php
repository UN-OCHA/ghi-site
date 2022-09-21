<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;

/**
 * Provides an attachment unit item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "attachment_unit",
 *   label = @Translation("Attachment unit"),
 *   description = @Translation("This item displays the unit of an attachment."),
 * )
 */
class AttachmentUnit extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    return $this->t('Unit');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getContextValue('attachment');
    if (!$attachment || !$attachment instanceof AttachmentInterface) {
      return NULL;
    }
    return $attachment->unit->label ?? NULL;
  }

}
