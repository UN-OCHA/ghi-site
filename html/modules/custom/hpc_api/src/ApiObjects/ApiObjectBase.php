<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_api\Helpers\ArrayHelper;

/**
 * Base class for API objects.
 */
abstract class ApiObjectBase implements ApiObjectInterface, CacheableDependencyInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use CacheableDependencyTrait;

  /**
   * The id.
   *
   * @var int
   */
  protected int $id;

  /**
   * The raw data.
   *
   * @var mixed
   */
  protected mixed $rawData;

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    $this->id = (int) $data->Id;
    $this->rawData = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): int {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public static function getGraphQlItems() {
    try {
      $items = (new \ReflectionClassConstant(get_called_class(), 'GRAPHQL_ITEMS'))->getValue();
      assert(is_array($items));
      return $items;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData() {
    return $this->rawData ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getObjectLookupProperties(): array {
    try {
      $items = (new \ReflectionClassConstant(get_called_class(), 'LOOKUP_PROPERTIES'))->getValue();
      assert(is_array($items));
      return $items;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getObjectStorageKey(): string {
    try {
      $object_storage_key = (new \ReflectionClassConstant(get_called_class(), 'OBJECT_STORAGE_KEY'))->getValue();
      if ($object_storage_key) {
        return $object_storage_key;
      }
    }
    catch (\Exception $e) {
      // Fail silently.
    }
    $parts = explode('\\', static::class);
    $class_name = end($parts);
    return ucfirst(strtolower($class_name) . 'ObjectStorage');
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $reflect = new \ReflectionClass($this);
    $array = [];
    $exclude_properties = [
      'stringTranslation',
      'cacheMaxAge',
      'rawData',
    ];
    foreach ($reflect->getProperties() as $property) {
      $key = $property->getName();
      if ($property->isPrivate() || in_array($key, $exclude_properties)) {
        continue;
      }
      $item = $this->$key;
      if (is_object($item) && method_exists($item, 'toArray')) {
        $array[$key] = $item->toArray();
      }
      elseif (is_array($item)) {
        foreach ($item as $_key => $_item) {
          if (is_object($_item) && method_exists($_item, 'toArray')) {
            $array[$key][$_key] = $_item->toArray();
          }
          elseif (is_array($_item) || is_scalar($_item)) {
            $array[$key][$_key] = $_item;
          }
        }
      }
      else {
        $array[$key] = $item;
      }
    }

    // Classes can define a constant to exclude specific array keys from being
    // transformed.
    $exclude_keys = $reflect->getConstant('MAINTAIN_ARRAY_KEYS') ?: [];
    ArrayHelper::transformKeysToUnderscore($array, $exclude_keys);

    return $array;
  }

  /**
   * Set the cache tags for this object.
   *
   * @param array $cache_tags
   *   The cache tags for this object.
   */
  public function setCacheTags($cache_tags) {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $cache_tags);
  }

}
