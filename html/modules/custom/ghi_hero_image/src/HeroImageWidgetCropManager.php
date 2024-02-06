<?php

namespace Drupal\ghi_hero_image;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\crop\Entity\CropType;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\ghi_hero_image\Plugin\Field\FieldType\HeroImageItem;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\image_widget_crop\ImageWidgetCropManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * HeroImageWidgetCropManager class.
 *
 * This builds on top of the ImageWidgetCropManager class to allow cropping on
 * external images. The main idea is to remove the storage of a file id in the
 * crop table (crop_field_data), because we don't have that for non-local
 * images. This is handled via \Drupal\ghi_image\CropManager
 * It also uses the ghi_image_crop form element to support this.
 *
 * @see \Drupal\ghi_image\CropManager
 */
class HeroImageWidgetCropManager extends ImageWidgetCropManager implements ImageWidgetCropInterface, ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * Define the supported crop types.
   */
  const CROP_TYPES = ['14x5', '16x9'];

  /**
   * Define the supported fields.
   */
  const IMAGE_FIELDS = ['field_image', 'field_hero_image'];

  /**
   * The hero image manager class.
   *
   * @var \Drupal\ghi_hero_image\HeroImageManager
   */
  public $heroImageManager;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The crop manager.
   *
   * @var \Drupal\ghi_image\CropManager
   */
  protected $cropManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
    $instance->heroImageManager = $container->get('hero_image.manager');
    $instance->mimeTypeGuesser = $container->get('file.mime_type.guesser');
    $instance->cropManager = $container->get('ghi_image.crop_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function applyCrop(array $properties, $field_value, CropType $crop_type) {
    $this->cropManager->applyCrop($properties, $field_value, $crop_type, $this->imageWidgetCropSettings->get('settings.notify_apply'));
  }

  /**
   * {@inheritdoc}
   */
  public function updateCrop(array $properties, $field_value, CropType $crop_type) {
    return $this->cropManager->updateCrop($properties, $field_value, $crop_type);
  }

  /**
   * {@inheritdoc}
   */
  public function saveCrop(array $crop_properties, $field_value, CropType $crop_type, $notify = TRUE) {
    $this->cropManager->saveCrop($crop_properties, $field_value, $crop_type, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCrop($file_uri, CropType $crop_type, $file_id) {
    $this->cropManager->deleteCrop($file_uri, $crop_type, $file_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCropOriginalDimension(array $field_values, array $properties) {
    return $this->cropManager->getCropOriginalDimension($field_values, $properties);
  }

  /**
   * Alter a content entity form to add the image crop functionality.
   */
  public function contentEntityFormAlter(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }

    foreach (self::IMAGE_FIELDS as $field_name) {
      $entity = $form_object->getEntity();
      if (!$entity || !$entity->hasField($field_name)) {
        continue;
      }

      // If a newer value for the image is avialble, use that.
      if ($form_state->hasValue($field_name)) {
        $entity->get($field_name)->setValue($form_state->getValue($field_name));
      }

      $file = NULL;
      $field = $entity->get($field_name);
      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field_image */
      if ($field instanceof FileFieldItemList) {
        $field = $entity->get($field_name);
        $files = $field->referencedEntities();
        $file = $files[0] ?? NULL;
      }
      elseif (($field->get(0) ?? NULL) instanceof HeroImageItem && $image_properties = $this->heroImageManager->getImageProperties($field)) {
        $file = File::create([
          'filename' => basename($image_properties['image_url']),
          'uri' => $image_properties['file_uri'],
          'filemime' => $this->mimeTypeGuesser->guessMimeType($image_properties['image_url']),
          'status' => 1,
        ]);
      }

      if (!$file) {
        continue;
      }

      $crop_types = self::CROP_TYPES;

      $form['image_crop'] = [
        '#type' => 'container',
        'image_crop' => [
          '#type' => 'ghi_image_crop',
          '#file' => $file,
          '#crop_type_list' => $crop_types,
          '#crop_preview_image_style' => 'crop_thumbnail',
          '#show_default_crop' => TRUE,
          '#show_crop_area' => TRUE,
          '#warn_mupltiple_usages' => TRUE,
        ],
      ];
    }
    $form['actions']['submit']['#submit'][] = [$this, 'contentEntityFormCropSubmit'];
  }

  /**
   * Submit handler for content entity forms using the crop api.
   */
  public function contentEntityFormCropSubmit(array &$form, FormStateInterface $form_state) {
    if (empty($form['image_crop'])) {
      return;
    }
    $crop_type_names = self::CROP_TYPES;
    $form_state_values = $form_state->getValues();
    $file = $form['image_crop']['image_crop']['#file'] ?? NULL;
    if (!$file) {
      return;
    }

    foreach ($crop_type_names as $crop_type_name) {
      $this->cropManager->processCropSubmit($file->getFileUri(), $crop_type_name, $form_state_values['image_crop']);
    }
  }

}
