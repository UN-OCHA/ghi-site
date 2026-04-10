<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\ApiObjects\Resource;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'resource' fabric query.
 */
#[FabricQuery(
  id: 'resource',
  label: new TranslatableMarkup('Resource query'),
)]
class ResourceQuery extends FabricQueryBase {

  /**
   * Get a resource by its id.
   *
   * @param int $resource_id
   *   The resource id.
   *
   * @return \Drupal\hpc_api\ApiObjects\Resource|null
   *   The resource object or NULL if not found.
   */
  public function getResource(int $resource_id): ?Resource {
    $resource = $this->objectStore->getObject($resource_id, Resource::getObjectStorageKey());
    if ($resource) {
      return $resource;
    }
    // Get the resource data.
    $items = $this->fabricClient->createQuery('resources', Resource::getGraphQlItems(), NULL, 1)
      ->setFilter('Id', $resource_id)
      ->execute() ?: [];
    $item = count($items) == 1 ? reset($items) : NULL;
    if (!$item) {
      return NULL;
    }
    $resource = new Resource($item);
    $this->objectStore->addObject($resource);
    return $resource;
  }

  /**
   * Get a resource by object.
   *
   * @param string $object_type
   *   The object type.
   * @param int $object_id
   *   The object id.
   *
   * @return \Drupal\hpc_api\ApiObjects\Resource[]
   *   An array of resource objects.
   */
  public function getResourcesByObject(string $object_type, int $object_id) {
    $storage_key = Resource::getObjectStorageKey() . '::' . $object_type;
    $collection_key = match ($object_type) {
      'plan' => 'PlanId',
      'governing_entity' => 'FieldClusterId',
    };
    if (!$collection_key) {
      throw new \InvalidArgumentException(sprintf('Unsupported object type %s', $object_type));
    }

    $resource = $this->objectStore->getObjectCollection($storage_key, $collection_key, $object_id);
    if ($resource) {
      return $resource;
    }
    $items = [];
    $request_items = ['resource' => Resource::getGraphQlItems()];
    switch ($object_type) {
      case 'plan':
        $items = $this->fabricClient->createQuery('planResources', array_merge(['PlanId'], $request_items))
          ->setFilter('PlanId', $object_id)
          ->execute() ?: [];
        break;

      case 'governing_entity':
        $items = $this->fabricClient->createQuery('fieldClusterResources', array_merge(['FieldClusterId'], $request_items))
          ->setFilter('FieldClusterId', $object_id)
          ->execute() ?: [];
        break;

    }
    if (empty($items)) {
      return [];
    }

    // Add a collection key for storage in object collections.
    $items = array_map(function ($item) use ($collection_key, $object_id) {
      $item->resource->$collection_key = $object_id;
      return $item;
    }, $items);
    $resources = $this->buildResultObjects(array_map(fn ($item) => $item->resource, $items), Resource::class);
    $this->objectStore->addObjectCollection($resources, $storage_key, $collection_key);
    $resource_ids = $this->extractIds($resources);
    return array_combine($resource_ids, $resources);
  }

}
