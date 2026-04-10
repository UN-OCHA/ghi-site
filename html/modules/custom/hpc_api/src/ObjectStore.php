<?php

namespace Drupal\hpc_api;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;
use Drupal\hpc_api\Traits\ObjectFilterTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Class representing an object store for Api objects.
 */
class ObjectStore {

  use SimpleCacheTrait;
  use ObjectFilterTrait;

  /**
   * Flag to inidicate if the object store should be used or not.
   *
   * @var bool
   */
  protected bool $enabled = TRUE;

  /**
   * Get the ids already requested, identified by key.
   *
   * @param string $storage_key
   *   The storage key.
   * @param array $ids
   *   An array of ids.
   * @param string $key
   *   The key by which the request ids are stored.
   */
  public function addRequestedIds(string $storage_key, array $ids, ?string $key = NULL): void {
    if (!$this->enabled) {
      return;
    }
    $storage = $this->cache($storage_key) ?: [];
    $key = $key ?? 'id';
    $storage['requested_ids'] = $storage['requested_ids'] ?? [];
    $storage['requested_ids'][$key] = $storage['requested_ids'][$key] ?? [];
    $storage['requested_ids'][$key] = array_unique(array_merge($storage['requested_ids'][$key], $ids));
    $this->cache($storage_key, $storage);
  }

  /**
   * Get the ids already requested, identified by key.
   *
   * @param string $storage_key
   *   The storage key.
   * @param string $key
   *   The key by which the request ids are stored.
   *
   * @return int[]
   *   An array of ids.
   */
  public function getRequestedIds(string $storage_key, string $key) {
    if (!$this->enabled) {
      return [];
    }
    $storage = $this->cache($storage_key) ?: [];
    return $storage['requested_ids'][$key] ?? [];
  }

  /**
   * Add an item to the object storage.
   *
   * @param \Drupal\hpc_api\ApiObjects\ApiObjectInterface $object
   *   The object to store.
   */
  public function addObject(ApiObjectInterface $object): void {
    if (!$this->enabled) {
      return;
    }
    $storage = $this->cache($object->getObjectStorageKey()) ?: [];
    $storage['objects'] = $storage['objects'] ?? [];
    $storage['objects'][$object->id()] = $object;
    // Create a lookup.
    foreach ($object->getObjectLookupProperties() as $property_name) {
      $property_value = $object->getRawData()?->$property_name ?? NULL;
      $method = 'get' . ucfirst($property_name);
      if (!$property_value && method_exists($object, $method)) {
        $property_value = $object->$method() ?? NULL;
      }
      if (empty($property_value) || !is_scalar($property_value)) {
        continue;
      }
      $storage[$property_name] = $storage[$property_name] ?? [];
      $storage[$property_name][$property_value] = $storage[$property_name][$property_value] ?? [];
      $storage[$property_name][$property_value][$object->id()] = $object->id();
    }
    $this->cache($object->getObjectStorageKey(), $storage);
  }

  /**
   * Add multiple items to the object storage.
   *
   * @param \Drupal\hpc_api\ApiObjects\ApiObjectInterface[] $objects
   *   The objects to store.
   */
  public function addObjects(array $objects): void {
    if (!$this->enabled) {
      return;
    }
    foreach ($objects as $object) {
      $this->addObject($object);
    }
  }

  /**
   * Get an item from the object storage.
   *
   * @param int $key
   *   The key of the object to fetch, value lookup depends on $property.
   * @param string $storage_key
   *   The storage key identifier from which to load the object.
   *
   * @return \Drupal\hpc_api\ApiObjects\ApiObjectInterface|null
   *   The object if found, NULL otherwise.
   */
  public function getObject(int $key, string $storage_key): ?ApiObjectInterface {
    if (!$this->enabled) {
      return NULL;
    }
    $storage = $this->cache($storage_key) ?: [];
    $storage['objects'] = $storage['objects'] ?? [];
    return $storage['objects'][$key] ?? NULL;
  }

  /**
   * Get multiple items from the object storage.
   *
   * @param int[] $keys
   *   An array of object keys to lookup.
   * @param string $storage_key
   *   The storage key identifier from which to load the object.
   * @param string|null $property
   *   The property to lookup the object in the store.
   * @param array $filter
   *   Optional filter array to filter the objects retrieved from the object
   *   store.
   *
   * @return \Drupal\hpc_api\ApiObjects\ApiObjectInterface[]
   *   An array of objects.
   *
   * @throws InvalidArgumentException
   */
  public function getObjects(array $keys, string $storage_key, ?string $property = NULL, array $filter = []): array {
    if (!$this->enabled) {
      return [];
    }
    $storage = $this->cache($storage_key) ?: [];
    $storage['objects'] = $storage['objects'] ?? [];
    if ($property === NULL) {
      return array_intersect_key($storage['objects'], array_flip($keys));
    }

    $object_ids = [];
    foreach (array_intersect_key($storage[$property] ?? [], array_flip($keys)) as $ids) {
      $object_ids += $ids;
    }
    $objects = !empty($object_ids) ? $this->getObjects($object_ids, $storage_key) : [];
    if (!empty($objects) && !empty($filter)) {
      $this->filterObjects($objects, $filter);
    }
    return $objects;
  }

  /**
   * Get all stored objects for the given storage key.
   *
   * @param string $storage_key
   *   The storage key identifier from which to load the object.
   *
   * @return \Drupal\hpc_api\ApiObjects\ApiObjectInterface[]
   *   An array of objects.
   */
  public function getAllObjects(string $storage_key) {
    if (!$this->enabled) {
      return [];
    }
    $storage = $this->cache($storage_key) ?: [];
    return $storage['objects'] ?? [];
  }

  /**
   * Add an object collection to the object storage using a custom key.
   *
   * @param \Drupal\hpc_api\ApiObjects\ApiObjectInterface[] $objects
   *   The objects to store.
   * @param string $storage_key
   *   The storage key identifier for the objects.
   * @param string $collection_key
   *   The collection key identifier for the objects.
   */
  public function addObjectCollection(array $objects, string $storage_key, string $collection_key): void {
    if (!$this->enabled) {
      return;
    }
    $storage = $this->cache($storage_key) ?? [];
    $storage['collections'] = $storage['collections'] ?? [];
    $collection_key_method = 'get' . ucfirst(strtolower($collection_key));
    foreach ($objects as $object) {
      assert($object instanceof ApiObjectInterface);
      $property_value = $object->getRawData()?->$collection_key ?? NULL;
      if (!$property_value && method_exists($object, $collection_key_method)) {
        $property_value = $object->$collection_key_method();
      }
      if (!$property_value) {
        throw new \InvalidArgumentException('The collection key ' . $collection_key . ' is invalid on object of type ' . get_class($object));
      }
      $storage['collections'][$collection_key][$property_value] = $storage['collections'][$collection_key][$property_value] ?? [];
      $storage['collections'][$collection_key][$property_value][$object->id()] = $object->id();
    }
    $this->cache($storage_key, $storage);
    $this->addObjects($objects);
  }

  /**
   * Get an object collection from the object storage using a custom key.
   *
   * @param string $storage_key
   *   The storage key identifier for the objects.
   * @param string $collection_key
   *   The collection key identifier for the objects.
   * @param int|string $collection_value
   *   The collection value under which the objects are stored.
   *
   * @return \Drupal\hpc_api\ApiObjects\ApiObjectInterface[]
   *   An array of objects.
   */
  public function getObjectCollection(string $storage_key, string $collection_key, int|string $collection_value): array {
    if (!$this->enabled) {
      return [];
    }
    $storage = $this->cache($storage_key) ?? [];
    $storage['collections'] = $storage['collections'] ?? [];
    $object_ids = $storage['collections'][$collection_key][$collection_value] ?? [];
    return !empty($object_ids) ? $this->getObjects($object_ids, $storage_key) : [];
  }

  /**
   * Disable the object store.
   */
  public function disable(): void {
    $this->enabled = FALSE;
  }

}
