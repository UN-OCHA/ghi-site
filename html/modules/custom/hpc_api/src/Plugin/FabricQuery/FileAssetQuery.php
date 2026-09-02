<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\ApiObjects\FileAsset;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'file_asset' fabric query.
 */
#[FabricQuery(
  id: 'file_asset',
  label: new TranslatableMarkup('File asset query'),
)]
class FileAssetQuery extends FabricQueryBase {

  /**
   * Get a file asset by its id.
   *
   * @param int $id
   *   The file asset id.
   *
   * @return \Drupal\hpc_api\ApiObjects\FileAsset|null
   *   The file asset object or NULL if not found.
   */
  public function getFileAsset(int $id): ?FileAsset {
    $file_asset = $this->objectStore->getObject($id, FileAsset::getObjectStorageKey());
    if ($file_asset) {
      return $file_asset;
    }
    // Get the file asset data.
    $items = $this->fabricClient->createQuery('fileAssets', FileAsset::getGraphQlItems(), NULL, 1)
      ->setFilter('Id', $id)
      ->execute() ?: [];
    $item = count($items) == 1 ? reset($items) : NULL;
    if (!$item) {
      return NULL;
    }
    $file_asset = new FileAsset($item);
    $this->objectStore->addObject($file_asset);
    return $file_asset;
  }

  /**
   * Get a file asset by object.
   *
   * @param string $object_type
   *   The object type.
   * @param int $object_id
   *   The object id.
   *
   * @return \Drupal\hpc_api\ApiObjects\FileAsset[]
   *   An array of file asset objects.
   */
  public function getFileAssetsByObject(string $object_type, int $object_id) {
    $storage_key = FileAsset::getObjectStorageKey() . '::' . $object_type;
    $collection_key = match ($object_type) {
      'plan' => 'PlanId',
      'governing_entity' => 'FieldClusterId',
    };
    if (!$collection_key) {
      throw new \InvalidArgumentException(sprintf('Unsupported object type %s', $object_type));
    }

    $file_asset = $this->objectStore->getObjectCollection($storage_key, $collection_key, $object_id);
    if ($file_asset) {
      return $file_asset;
    }
    $items = [];
    $request_items = FileAsset::getGraphQlItems();
    $filters = NULL;
    switch ($object_type) {
      case 'plan':
        $filters = ['plan' => ['Id' => $object_id]];
        // $request_items['plan'] = ['items' => ['Id']];
        break;

      case 'governing_entity':
        $filters = ['coordinationEntity' => ['Id' => $object_id]];
        // $request_items['coordinationEntity'] = ['items' => ['Id']];
        break;

    }

    if ($filters === NULL) {
      return [];
    }

    $items = $this->fabricClient->createQuery('fileAssets', $request_items)
      ->setFilters($filters)
      ->execute() ?: [];

    if (empty($items)) {
      return [];
    }

    // Add a collection key for storage in object collections.
    $items = array_map(function ($item) use ($collection_key, $object_id) {
      $item->$collection_key = $object_id;
      return $item;
    }, $items);
    $file_assets = $this->buildResultObjects($items, FileAsset::class);
    $this->objectStore->addObjectCollection($file_assets, $storage_key, $collection_key);
    $file_asset_ids = $this->extractIds($file_assets);
    return array_combine($file_asset_ids, $file_assets);
  }

}
