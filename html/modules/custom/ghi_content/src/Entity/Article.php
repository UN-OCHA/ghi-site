<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Bundle class for section nodes.
 */
class Article extends ContentBase {

  /**
   * Get the chapter of the article.
   *
   * @return string|null
   *   The chapter as a plain string or NULL.
   */
  public function getChapter() {
    return $this->get('field_chapter')->value ?? NULL;
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
