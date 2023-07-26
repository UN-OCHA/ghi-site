<?php

namespace Drupal\ghi_sections\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;
use Drupal\ghi_sections\Menu\SectionMenuItemInterface;

/**
 * Provides a data type wrapping a SectionMenuItemInterface.
 *
 * @DataType(
 *   id = "section_menu",
 *   label = @Translation("Section Menu"),
 *   description = @Translation("A section menu"),
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class SectionMenuData extends TypedData {

  /**
   * The section object.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuItemInterface
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    if ($value && !$value instanceof SectionMenuItemInterface) {
      throw new \InvalidArgumentException(sprintf('Value assigned to "%s" is not a valid section menu', $this->getName()));
    }
    parent::setValue($value, $notify);
  }

}
