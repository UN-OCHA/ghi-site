<?php

namespace Drupal\ghi_hero_image\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\file_entity\Entity\FileEntity;
use Drupal\image_widget_crop\Element\ImageCrop;

/**
 * Provides a form element for crop.
 *
 * This extends the ImageCrop element to remove a failing validation check that
 * would prevent cropping to work with externally hosted images.
 *
 * @FormElement("hero_image_crop")
 */
class HeroImageCrop extends ImageCrop {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#process' => [
        [static::class, 'processCrop'],
      ],
      '#file' => NULL,
      '#crop_preview_image_style' => 'crop_thumbnail',
      '#crop_type_list' => [],
      '#crop_types_required' => [],
      '#warn_multiple_usages' => FALSE,
      '#show_default_crop' => TRUE,
      '#show_crop_area' => FALSE,
      '#attached' => [
        'library' => [
          'image_widget_crop/cropper.integration',
        ],
      ],
      '#element_validate' => [[self::class, 'cropRequired']],
      '#tree' => TRUE,
    ];
  }

  /**
   * Render API callback: Expands the image_crop element type.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   form actions container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processCrop(array &$element, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $element['#file'];
    if (!empty($file) && preg_match('/image/', $file->getMimeType())) {
      $crop_type_list = $element['#crop_type_list'];
      // Display all crop types if none is selected.
      if (empty($crop_type_list)) {
        /** @var \Drupal\image_widget_crop\ImageWidgetCropInterface $iwc_manager */
        $iwc_manager = \Drupal::service('image_widget_crop.manager');
        $available_crop_types = $iwc_manager->getAvailableCropType(CropType::getCropTypeNames());
        $crop_type_list = array_keys($available_crop_types);
      }
      $element['crop_wrapper'] = [
        '#type' => 'details',
        '#title' => t('Crop image'),
        '#attributes' => [
          'class' => ['image-data__crop-wrapper'],
          'data-drupal-iwc' => 'wrapper',
        ],
        '#open' => $element['#show_crop_area'],
        '#weight' => 100,
      ];

      if ($element['#warn_multiple_usages']) {
        // Warn the user if the crop is used more than once.
        $usage_counter = self::countFileUsages($file);
        if ($usage_counter > 1) {
          $element['crop_reuse'] = [
            '#type' => 'container',
            '#markup' => t('This crop definition affects more usages of this image'),
            '#attributes' => [
              'class' => ['messages messages--warning'],
            ],
            '#weight' => -10,
          ];
        }
      }

      // Ensure that the ID of an element is unique.
      $list_id = \Drupal::service('uuid')->generate();

      $element['crop_wrapper'][$list_id] = [
        '#type' => 'vertical_tabs',
        '#parents' => [$list_id],
      ];

      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $crop_type_storage */
      $crop_type_storage = \Drupal::entityTypeManager()
        ->getStorage('crop_type');

      /** @var \Drupal\crop\Entity\CropType[] $crop_types */
      if ($crop_types = $crop_type_storage->loadMultiple($crop_type_list)) {
        foreach ($crop_types as $type => $crop_type) {
          $ratio = $crop_type->getAspectRatio() ?: 'NaN';
          $title = self::isRequiredType($element, $type) ? t('@label (required)', ['@label' => $crop_type->label()]) : $crop_type->label();
          $element['crop_wrapper'][$type] = [
            '#type' => 'details',
            '#title' => $title,
            '#group' => $list_id,
            '#attributes' => [
              'data-drupal-iwc' => 'type',
              'data-drupal-iwc-id' => $type,
              'data-drupal-iwc-ratio' => $ratio,
              'data-drupal-iwc-required' => self::isRequiredType($element, $type),
              'data-drupal-iwc-show-default-crop' => $element['#show_default_crop'] ? 'true' : 'false',
              'data-drupal-iwc-soft-limit' => Json::encode($crop_type->getSoftLimit()),
              'data-drupal-iwc-hard-limit' => Json::encode($crop_type->getHardLimit()),
              'data-drupal-iwc-original-width' => ($file instanceof FileEntity) ? $file->getMetadata('width') : getimagesize($file->getFileUri())[0],
              'data-drupal-iwc-original-height' => ($file instanceof FileEntity) ? $file->getMetadata('height') : getimagesize($file->getFileUri())[1],
            ],
          ];

          // Generation of html List with image & crop information.
          $element['crop_wrapper'][$type]['crop_container'] = [
            '#id' => $type,
            '#type' => 'container',
            '#attributes' => ['class' => ['crop-preview-wrapper', $list_id]],
            '#weight' => -10,
          ];

          $element['crop_wrapper'][$type]['crop_container']['image'] = [
            '#theme' => 'imagecache_external',
            '#style_name' => $element['#crop_preview_image_style'],
            '#attributes' => [
              'class' => ['crop-preview-wrapper__preview-image'],
              'data-drupal-iwc' => 'image',
            ],
            '#uri' => $file->getFileUri(),
            '#weight' => -10,
          ];

          $element['crop_wrapper'][$type]['crop_container']['reset'] = [
            '#type' => 'button',
            '#value' => t('Reset crop'),
            '#attributes' => [
              'class' => ['crop-preview-wrapper__crop-reset'],
              'data-drupal-iwc' => 'reset',
            ],
            '#weight' => -10,
          ];

          // Generation of html List with image & crop information.
          $element['crop_wrapper'][$type]['crop_container']['values'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['crop-preview-wrapper__value']],
            '#weight' => -9,
          ];

          // Element to track whether cropping is applied or not.
          $element['crop_wrapper'][$type]['crop_container']['values']['crop_applied'] = [
            '#type' => 'hidden',
            '#attributes' => [
              'data-drupal-iwc-value' => 'applied',
              'data-drupal-iwc-id' => $type,
            ],
            '#default_value' => 0,
          ];
          $edit = FALSE;
          $properties = [];
          $form_state_values = $form_state->getValue($element['#parents']);
          // Check if form state has values.
          if (self::hasCropValues($element, $type, $form_state)) {
            $form_state_properties = $form_state_values['crop_wrapper'][$type]['crop_container']['values'];
            // If crop is applied by the form state we keep it that way.
            if ($form_state_properties['crop_applied'] == '1') {
              $element['crop_wrapper'][$type]['crop_container']['values']['crop_applied']['#default_value'] = 1;
              $edit = TRUE;
            }
            $properties = $form_state_properties;
          }

          /** @var \Drupal\crop\CropInterface $crop */
          $crop = Crop::findCrop($file->getFileUri(), $type);
          if ($crop) {
            $edit = TRUE;
            /** @var \Drupal\image_widget_crop\ImageWidgetCropInterface $iwc_manager */
            $iwc_manager = \Drupal::service('image_widget_crop.manager');
            $original_properties = $iwc_manager->getCropProperties($crop);

            // If form state values have the same values that were saved or if
            // form state has no values yet and there are saved values then we
            // use the saved values.
            $properties = $original_properties == $properties || empty($properties) ? $original_properties : $properties;
            $element['crop_wrapper'][$type]['crop_container']['values']['crop_applied']['#default_value'] = 1;
            // If the user edits an entity and while adding new images resets an
            // saved crop we keep it reset.
            if (isset($properties['crop_applied']) && $properties['crop_applied'] == '0') {
              $element['crop_wrapper'][$type]['crop_container']['values']['crop_applied']['#default_value'] = 0;
            }
          }
          self::getCropFormElement($element, 'crop_container', $properties, $edit, $type);
        }
        // Stock Original File Values.
        $element['file-uri'] = [
          '#type' => 'value',
          '#value' => $file->getFileUri(),
        ];
      }
    }
    return $element;
  }

}
