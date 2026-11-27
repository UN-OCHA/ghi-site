<?php

namespace Drupal\ghi_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_content\Entity\ContentReviewInterface;

/**
 * Displays the same review explanations as the article edit form.
 */
#[FieldFormatter(
  id: 'ghi_review_reasons',
  label: new TranslatableMarkup('Review reasons'),
  field_types: ['string'],
)]
class ReviewReasonsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === ContentReviewInterface::REVIEW_REASONS_FIELD;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $entity = $items->getEntity();
    $reasons = $entity instanceof ContentReviewInterface ? $entity->getReviewReasons() : [];
    $elements = [];
    foreach ($items as $delta => $item) {
      if (isset($reasons[$item->value])) {
        $elements[$delta] = [
          '#theme' => 'ghi_review_reason',
          '#reason' => $item->value,
          '#description' => $reasons[$item->value],
        ];
      }
    }
    return $elements;
  }

}
