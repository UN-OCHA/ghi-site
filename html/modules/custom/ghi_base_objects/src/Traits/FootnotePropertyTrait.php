<?php

namespace Drupal\ghi_base_objects\Traits;

use Drupal\Core\Field\FieldItemList;

/**
 * Helper trait for handling footnote properties.
 */
trait FootnotePropertyTrait {

  /**
   * Get the property options for footnotes.
   *
   * @return string[]
   *   An array of property labels, keyed by their machine name.
   */
  public function getFootnotePropertyOptions() {
    $options = [
      'in_need' => $this->t('In need'),
      'target' => $this->t('Target'),
      'estimated_reach' => $this->t('Estimated reach'),
      'requirements' => $this->t('Requirements'),
      'funding' => $this->t('Funding'),
    ];
    return array_map(function ($option) {
      return (string) $option;
    }, $options);
  }

  /**
   * Get a specific footnote from a field item list.
   *
   * @param \Drupal\Core\Field\FieldItemList $item_list
   *   The field item list that holds the footnotes.
   * @param string $property
   *   The specific footnote to get.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The footnote content.
   */
  public function getFootnoteFromItemList(FieldItemList $item_list, $property) {
    foreach ($item_list->getIterator() as $value) {
      if ($value->property == $property) {
        return $value->footnote;
      }
    }
    return NULL;
  }

}
