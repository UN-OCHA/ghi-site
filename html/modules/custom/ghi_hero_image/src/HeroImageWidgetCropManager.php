<?php

namespace Drupal\ghi_hero_image;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\ghi_hero_image\Plugin\Field\FieldType\HeroImageItem;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\image_widget_crop\ImageWidgetCropManager;
use Drupal\webp\Webp;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * HeroImageWidgetCropManager class.
 *
 * This builds on top of the ImageWidgetCropManager class to allow cropping on
 * external images. The main idea is to remove the storage of a file id in the
 * crop table (crop_field_data), because we don't have that for non-local
 * images.
 * It also uses the hero_image_crop form element to support this.
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
   * Constructs a ImageWidgetCropManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\ghi_hero_image\HeroImageManager $hero_image_manager
   *   The hero image manager.
   * @param \Drupal\webp\Webp $webp
   *   The webp service.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mime_type_guesser
   *   The MIME type guesser.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, HeroImageManager $hero_image_manager, Webp $webp, MimeTypeGuesserInterface $mime_type_guesser) {
    parent::__construct($entity_type_manager, $config_factory);
    $this->heroImageManager = $hero_image_manager;
    $this->webp = $webp;
    $this->mimeTypeGuesser = $mime_type_guesser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('hero_image.manager'),
      $container->get('webp.webp'),
      $container->get('file.mime_type.guesser'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyCrop(array $properties, $field_value, CropType $crop_type) {
    $crop_properties = $this->getCropOriginalDimension($field_value, $properties);
    if (!empty($crop_properties)) {
      $this->saveCrop(
        $crop_properties,
        $field_value,
        $crop_type,
        $this->imageWidgetCropSettings->get('settings.notify_apply')
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
      $image_styles = $this->getImageStylesByCrop($crop_type->id());
      if (!empty($image_styles)) {
        $crops = $this->loadImageStyleByCrop($image_styles, $crop_type, $field_value['file-uri']);
      }

      if (empty($crops)) {
        $this->saveCrop($crop_properties, $field_value, $crop_type, $this->imageWidgetCropSettings->get('settings.notify_update'));
        return TRUE;
      }

      /** @var \Drupal\crop\Entity\Crop $crop */
      foreach ($crops as $crop) {
        if (!$this->cropHasChanged($crop_properties, array_merge($crop->position(), $crop->size()))) {
          continue;
        }

        $this->updateCropProperties($crop, $crop_properties);
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
    $image_styles = $this->getImageStylesByCrop($crop_type->id());
    $crop = $this->cropStorage->loadByProperties([
      'type' => $crop_type->id(),
      'uri' => $file_uri,
    ]);
    $this->cropStorage->delete($crop);
    $this->imageStylesOperations($image_styles, $file_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getCropOriginalDimension(array $field_values, array $properties) {
    $crop_coordinates = [];

    // Get Center coordinate of crop zone on original image.
    $axis_coordinate = $this->getAxisCoordinates(
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

      $crop_types = HeroImageWidgetCropManager::CROP_TYPES;

      $form['image_crop'] = [
        '#type' => 'container',
        'image_crop' => [
          '#type' => 'hero_image_crop',
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
    $crop_type_names = self::CROP_TYPES;
    $form_state_values = $form_state->getValues();
    $file = $form['image_crop']['image_crop']['#file'];
    if (!$file) {
      return;
    }

    foreach ($crop_type_names as $crop_type_name) {
      $flush_styles = FALSE;
      $properties = $form_state_values['image_crop']['crop_wrapper'][$crop_type_name]['crop_container']['values'];

      /** @var \Drupal\crop\Entity\CropType $crop_type */
      $crop_type = $this->cropTypeStorage->load($crop_type_name);

      $crop_exists = Crop::cropExists($file->getFileUri(), $crop_type_name);
      if (!$crop_exists) {
        if ($properties['crop_applied'] == '1' && isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
          $this->applyCrop($properties, $form_state_values['image_crop'], $crop_type);
          $flush_styles = TRUE;
        }
      }
      else {
        // Get all imagesStyle used this crop_type.
        /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
        $image_styles = $this->getImageStylesByCrop($crop_type_name);
        $crops = $this->loadImageStyleByCrop($image_styles, $crop_type, $file->getFileUri());
        // If the entity already exist & is not deleted by user update
        // $crop_type_name crop entity.
        if ($properties['crop_applied'] == '0' && !empty($crops)) {
          $this->deleteCrop($file->getFileUri(), $crop_type, NULL);
          $flush_styles = TRUE;
        }
        elseif (isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
          $changed = $this->updateCrop($properties, [
            'file-uri' => $file->getFileUri(),
          ], $crop_type);
          if ($changed) {
            $flush_styles = TRUE;
          }
        }
        if ($flush_styles) {
          foreach ($image_styles as $image_style) {
            // This should be sufficient.
            $image_style->flush($file->getFileUri());

            // And this is to make sure that it works in practice. We delete
            // the webp image if it exists.
            $derivative_uri = $image_style->buildUri($file->getFileUri());
            $webp_uri = $derivative_uri . '.webp';
            $this->heroImageManager->deleteImageFile($webp_uri);

            // And we create a new style derivative and the webp version that
            // belongs to it, so that they are immediately available.
            $image_style->createDerivative($file->getFileUri(), $derivative_uri);
            $this->webp->createWebpCopy($derivative_uri);
          }
        }
      }
    }
  }

}
