<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'ghi_hero_image' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_hero_image",
 *   label = @Translation("Hero image"),
 *   field_types = {"ghi_hero_image"},
 * )
 */
class HeroImageWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // @todo This needs an option to integrate the SmugMug API.
    $source_options = [
      'hpc_webcontent_file_attachment' => $this->t('HPC Webcontent File Attachment'),
    ];

    $element['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Source'),
      '#options' => $source_options,
      '#default_value' => isset($items[$delta]->source) ? $source_options[$items[$delta]->source] : array_key_first($source_options),
    ];

    // @todo This needs an actual configuration, based on the source selection.
    $element['settings'] = [
      '#type' => 'hidden',
      '#value' => 'test',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      $values[$delta]['settings'] = [];
    }
    return $values;
  }

}
