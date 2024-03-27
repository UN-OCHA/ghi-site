<?php

namespace Drupal\ghi_image;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\webp\Webp;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * CropManager calculation class.
 */
class CropManager {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * WebP driver.
   *
   * @var \Drupal\webp\Webp
   */
  protected $webp;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * Instance of API ImageWidgetCropManager.
   *
   * @var \Drupal\image_widget_crop\ImageWidgetCropInterface
   */
  protected $imageWidgetCropManager;

  /**
   * The crop storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cropStorage;

  /**
   * The crop storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $cropTypeStorage;

  /**
   * The image style storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The File storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * Constructs a ImageWidgetCropManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\webp\Webp $webp
   *   The webp service.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mime_type_guesser
   *   The mime type guesser.
   * @param \Drupal\image_widget_crop\ImageWidgetCropInterface $iwc_manager
   *   The image widget crop manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, Webp $webp, MimeTypeGuesserInterface $mime_type_guesser, ImageWidgetCropInterface $iwc_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->webp = $webp;
    $this->mimeTypeGuesser = $mime_type_guesser;
    $this->imageWidgetCropManager = $iwc_manager;
    $this->cropStorage = $this->entityTypeManager->getStorage('crop');
    $this->cropTypeStorage = $this->entityTypeManager->getStorage('crop_type');
    $this->imageStyleStorage = $this->entityTypeManager->getStorage('image_style');
    $this->fileStorage = $this->entityTypeManager->getStorage('file');
  }

  /**
   * {@inheritdoc}
   */
  public function applyCrop(array $properties, $field_value, CropType $crop_type, $notify = FALSE) {
    $crop_properties = $this->getCropOriginalDimension($field_value, $properties);
    if (!empty($crop_properties)) {
      $this->saveCrop(
        $crop_properties,
        $field_value,
        $crop_type,
        $notify
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateCrop(array $properties, $field_value, CropType $crop_type) {
    $crop_properties = $this->getCropOriginalDimension($field_value, $properties);
    $changed = FALSE;
    if (!empty($crop_properties)) {
      $image_styles = $this->imageWidgetCropManager->getImageStylesByCrop($crop_type->id());
      if (!empty($image_styles)) {
        $crops = $this->imageWidgetCropManager->loadImageStyleByCrop($image_styles, $crop_type, $field_value['file-uri']);
      }

      if (empty($crops)) {
        $this->saveCrop($crop_properties, $field_value, $crop_type, FALSE);
        return TRUE;
      }

      /** @var \Drupal\crop\Entity\Crop $crop */
      foreach ($crops as $crop) {
        if (!$this->imageWidgetCropManager->cropHasChanged($crop_properties, array_merge($crop->position(), $crop->size()))) {
          continue;
        }

        $this->imageWidgetCropManager->updateCropProperties($crop, $crop_properties);
        $changed = TRUE;
      }
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function saveCrop(array $crop_properties, $field_value, CropType $crop_type, $notify = TRUE) {
    $values = [
      'type' => $crop_type->id(),
      'entity_id' => NULL,
      'entity_type' => NULL,
      'uri' => $field_value['file-uri'],
      'x' => $crop_properties['x'],
      'y' => $crop_properties['y'],
      'width' => $crop_properties['width'],
      'height' => $crop_properties['height'],
    ];

    /** @var \Drupal\crop\CropInterface $crop */
    $crop = $this->cropStorage->create($values);
    $crop->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCrop($file_uri, CropType $crop_type, $file_id) {
    $image_styles = $this->imageWidgetCropManager->getImageStylesByCrop($crop_type->id());
    $crop = $this->cropStorage->loadByProperties([
      'type' => $crop_type->id(),
      'uri' => $file_uri,
    ]);
    $this->cropStorage->delete($crop);
    $this->imageWidgetCropManager->imageStylesOperations($image_styles, $file_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getCropOriginalDimension(array $field_values, array $properties) {
    $crop_coordinates = [];

    // Get Center coordinate of crop zone on original image.
    $axis_coordinate = $this->imageWidgetCropManager->getAxisCoordinates(
      ['x' => $properties['x'], 'y' => $properties['y']],
      ['width' => $properties['width'], 'height' => $properties['height']]
    );

    // Calculate coordinates (position & sizes) of crop zone on original image.
    $crop_coordinates['width'] = $properties['width'];
    $crop_coordinates['height'] = $properties['height'];
    $crop_coordinates['x'] = $axis_coordinate['x'];
    $crop_coordinates['y'] = $axis_coordinate['y'];

    return $crop_coordinates;
  }

  /**
   * Delete an image file.
   *
   * @param string $uri
   *   The URI to the file to delete.
   */
  public function deleteImageFile($uri) {
    $this->fileSystem->delete($uri);
  }

  /**
   * Process crop form submission.
   *
   * @param string $file_uri
   *   The file URI.
   * @param string $crop_type_name
   *   The crop type identifier.
   * @param array $form_values
   *   The submitted form values relevant for the crop.
   */
  public function processCropSubmit($file_uri, $crop_type_name, $form_values) {
    $flush_styles = FALSE;

    $properties = $form_values['crop_wrapper'][$crop_type_name]['crop_container']['values'];
    if (empty($properties)) {
      return;
    }

    /** @var \Drupal\crop\Entity\CropType $crop_type */
    $crop_type = $this->cropTypeStorage->load($crop_type_name);

    $crop_exists = Crop::cropExists($file_uri, $crop_type_name);
    if (!$crop_exists) {
      if ($properties['crop_applied'] == '1' && isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
        $this->applyCrop($properties, $form_values, $crop_type);
        $flush_styles = TRUE;
      }
    }
    else {
      // Get all imagesStyle used this crop_type.
      /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
      $image_styles = $this->imageWidgetCropManager->getImageStylesByCrop($crop_type_name);
      $crops = $this->imageWidgetCropManager->loadImageStyleByCrop($image_styles, $crop_type, $file_uri);
      // If the entity already exist & is not deleted by user update
      // $crop_type_name crop entity.
      if ($properties['crop_applied'] == '0' && !empty($crops)) {
        $this->deleteCrop($file_uri, $crop_type, NULL);
        $flush_styles = TRUE;
      }
      elseif (isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
        $changed = $this->updateCrop($properties, [
          'file-uri' => $file_uri,
        ], $crop_type);
        if ($changed) {
          $flush_styles = TRUE;
        }
      }
      if ($flush_styles) {
        foreach ($image_styles as $image_style) {
          // This should be sufficient.
          $image_style->flush($file_uri);

          // And this is to make sure that it works in practice. We delete
          // the webp image if it exists.
          $derivative_uri = $image_style->buildUri($file_uri);
          $webp_uri = $derivative_uri . '.webp';
          $this->deleteImageFile($webp_uri);

          // And we create a new style derivative and the webp version that
          // belongs to it, so that they are immediately available.
          $image_style->createDerivative($file_uri, $derivative_uri);
          $this->webp->createWebpCopy($derivative_uri);
        }
      }
    }
  }

}
