<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Provides an attachment unit item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'attachment_unit',
  label: new TranslatableMarkup('Attachment unit'),
  description: new TranslatableMarkup('This item displays the unit of an attachment.'),
)]
class AttachmentUnit extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'unit';

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    $attachment = $this->getContextValue('attachment');
    $plan = $attachment instanceof Attachment ? $attachment->getPlanObject() : $this->getContextValue('plan_object');
    return $this->t('Unit', [], $this->buildPlanTranslationOptions($plan instanceof Plan ? $plan : NULL));
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getContextValue('attachment');
    if (!$attachment || !$attachment instanceof Attachment) {
      return NULL;
    }
    $langcode = $attachment->getPlanLanguage();
    return $attachment->getUnitLabel($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof Attachment ? $attachment->getValueCacheTags() : [];
  }

}
