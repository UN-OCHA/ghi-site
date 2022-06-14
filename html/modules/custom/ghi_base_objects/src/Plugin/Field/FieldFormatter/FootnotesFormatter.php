<?php

namespace Drupal\ghi_base_objects\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Plugin implementation of the 'ghi_footnotes' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_footnotes",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_footnotes"}
 * )
 */
class FootnotesFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    return $element;
  }

}
