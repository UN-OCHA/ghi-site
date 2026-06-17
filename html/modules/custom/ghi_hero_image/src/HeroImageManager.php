<?php

namespace Drupal\ghi_hero_image;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\smugmug_api\Service\Image;

/**
 * Manager class for hero images.
 */
class HeroImageManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The attachment query.
   *
   * @var \Drupal\hpc_api\Plugin\FabricQuery\FileAssetQuery
   */
  public $fileAssetQuery;

  /**
   * The SmugMug image service.
   *
   * @var \Drupal\smugmug_api\Service\Image
   */
  public $smugmugImage;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a manager class for hero images.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FabricQueryManager $fabric_query_manager, Image $smugmug_image, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileAssetQuery = $fabric_query_manager->createInstance('file_asset');
    $this->smugmugImage = $smugmug_image;
    $this->fileSystem = $file_system;
  }

  /**
   * Get the default item source.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   *
   * @return string
   *   The item source as a string.
   */
  public function getDefaultItemSource(FieldItemListInterface $items) {
    if ($this->getHeroImageFileAsset($items)) {
      return 'hpc_webcontent_file_attachment';
    }
    return 'none';
  }

  /**
   * Get properties for a hero image.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param \Drupal\Core\Field\FormatterInterface $formatter
   *   The field formatter.
   *
   * @return array
   *   An array with the image properties.
   */
  public function getImageProperties(FieldItemListInterface $items, ?FormatterInterface $formatter = NULL) {
    if (PHP_SAPI === 'cli') {
      // Don't do anything when run from the command line.
      return NULL;
    }
    $image_url = NULL;
    $file_uri = NULL;
    $credit = NULL;
    $caption = NULL;

    $item = !$items->isEmpty() ? (object) $items->get(0)->getValue() ?? NULL : NULL;
    $item_source = $item ? $item->source : NULL;
    if (!$item_source) {
      $item_source = $this->getDefaultItemSource($items);
    }

    $item_settings = $item && property_exists($item, 'settings') && is_array($item->settings) ? ($item->settings[$item_source] ?? []) : [];
    switch ($item_source) {
      case 'hpc_webcontent_file_attachment':

        // Find the right file asset based on the configuration, or fallback to
        // the first available file asset.
        $file_assets = $this->getHeroImageFileAsset($items);
        $file_asset_id = $item_settings['file_asset_id'] ?? ($item_settings['attachment_id'] ?? NULL);
        if (!$file_asset_id || empty($file_assets[$file_asset_id])) {
          $file_asset_id = array_key_first($file_assets);
        }

        if ($file_asset_id && !empty($file_assets[$file_asset_id])) {
          $file_asset = $file_assets[$file_asset_id];
          $image_url = $file_asset->getUrl()->toString();
          $file_uri = imagecache_external_generate_path($image_url);
          $credit = $file_asset->getCredit();
        }
        break;

      case 'smugmug_api':
        $image_id = $item_settings['image_id'] ?? NULL;
        $image_urls = $image_id ? $this->smugmugImage->getImageSizes($image_id) : NULL;
        $image_url = $image_urls['X3LargeImageUrl'] ?? ($image_urls['LargestImageUrl'] ?? NULL);
        $file_uri = imagecache_external_generate_path($image_url);
        $image = $image_id ? $this->smugmugImage->getImage($image_id) : NULL;
        if ($image) {
          $caption_format_parents = ['FormattedValues', 'Caption', 'html'];
          $caption = NestedArray::getValue($image, $caption_format_parents) ?? ($image['Caption'] ?? NULL);
        }
        break;

      case 'inherit':
        return NULL;

      case 'none':
      case '':
        $default_image = $formatter?->getSetting('default_image');
        $image_path = $default_image['path'] ?? NULL;
        if (!empty($image_path)) {
          if ($default_image['use_image_style']) {
            // $image_path must be ready for
            // Drupal\image\Entity\ImageStyle::buildUri().
            // This needs a valid scheme.
            // As long as https://www.drupal.org/project/drupal/issues/1308152
            // is not fixed, files stored outside from public, private and
            // temporary directories have no scheme.
            // So that if our path has no scheme, we copy the file to the public
            // files directory and add it as scheme.
            if (!StreamWrapperManager::getScheme($image_path)) {
              $image_path = ltrim($image_path, '/');
              $file_uri = 'public://config_default_image/' . $image_path;
              $directory = $this->fileSystem->dirname($file_uri);
              $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
              if (!file_exists($file_uri)) {
                $image_path = $this->fileSystem->copy($image_path, $file_uri);
              }
              else {
                $image_path = $file_uri;
              }
            }
          }
          else {
            $formatter?->setSetting('image_style', FALSE);
          }

          $image_url = $image_path;
        }
        break;
    }

    return $image_url && $file_uri ? [
      'image_url' => $image_url,
      'file_uri' => $file_uri,
      'caption' => $caption,
      'credit' => $credit,
    ] : NULL;
  }

  /**
   * Get the file assets for the given items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   *
   * @return \Drupal\hpc_api\ApiObjects\FileAsset[]
   *   An array of attachment objects.
   */
  private function getHeroImageFileAsset(FieldItemListInterface $items): array {
    $entity = $items->getEntity();
    $base_object = BaseObjectHelper::getBaseObjectFromNode($entity, 'plan');
    if (!$base_object instanceof BaseObjectInterface) {
      return [];
    }
    return $this->fileAssetQuery->getFileAssetsByObject($base_object->bundle(), $base_object->getSourceId());
  }

  /**
   * Alter tokens for hero images.
   */
  public function tokenAlter(&$replacements, $node) {
    if (!$node->hasField('field_hero_image')) {
      return;
    }

    $image_properties = $this->getImageProperties($node->field_hero_image);
    if (empty($image_properties['file_uri'])) {
      return;
    }

    /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
    $image_styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    foreach ($image_styles as $id => $style) {
      $replacements['[node:field_hero_image:' . $id . ']'] = $style->buildUrl($image_properties['file_uri']);
    }
  }

}
