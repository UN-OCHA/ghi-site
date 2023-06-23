<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for subpage nodes.
 */
class Document extends ContentBase {

  /**
   * Get the document summary.
   *
   * @return string
   *   The content of the summary field.
   */
  public function getSummary() {
    return $this->get('field_summary')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageMetaData() {
    $metadata = [];
    $metadata[] = [
      '#markup' => new TranslatableMarkup('Published on @date', [
        '@date' => $this->getDateFormatter()->format($this->getCreatedTime(), 'custom', 'j F Y'),
      ]),
    ];
    $tags = $this->getDisplayTags();
    if (!empty($tags)) {
      $metadata[] = [
        '#markup' => new TranslatableMarkup('Keywords @keywords', [
          '@keywords' => implode(', ', $tags),
        ]),
      ];
    }
    if ($this->isPublished()) {
      $metadata[] = [
        '#theme' => 'social_links',
      ];
    }
    return $metadata;
  }

}
