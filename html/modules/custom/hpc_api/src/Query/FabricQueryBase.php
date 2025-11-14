<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\ApiObjects\Types\CategoryType;
use Drupal\hpc_api\ApiObjects\Types\EntityType;
use Drupal\hpc_api\ApiObjects\Types\PlanYear;
use Drupal\hpc_api\ApiObjects\Types\RelationshipType;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for endpoint query plugins.
 */
abstract class FabricQueryBase extends PluginBase implements FabricQueryPluginInterface, ContainerFactoryPluginInterface {

  use SimpleCacheTrait;
  use DependencySerializationTrait;

  /**
   * The endpoint query service.
   *
   * @var \Drupal\hpc_api\Query\FabricQuery
   */
  public $fabricQuery;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public $cache;

  /**
   * The cache tags.
   *
   * @var string[]
   */
  protected $cacheTags = [];

  /**
   * The base types.
   *
   * @var array|null
   */
  protected $baseTypes = NULL;

  /**
   * The plan years.
   *
   * @var array|null
   */
  protected $planYears = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FabricQueryBase {
    /** @var \Drupal\hpc_api\Query\FabricQueryBase $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fabricQuery = $container->get('hpc_api.fabric_query');
    $instance->user = $container->get('current_user');
    $instance->cache = $container->get('cache.data');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * Set the cache tags for this query.
   *
   * @param array $cache_tags
   *   The cache tags for the current query.
   */
  public function setCacheTags($cache_tags = []) {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $cache_tags);
  }

  /**
   * Get the cache tags for this query.
   *
   * @return array
   *   The cache tags for the current query.
   */
  public function getCacheTags() {
    $cache_tags = $this->cacheTags;
    return $cache_tags;
  }

  /**
   * Get cached data for the given cache key.
   *
   * @param string $cache_key
   *   The cache key.
   *
   * @return mixed
   *   The cached data if available.
   */
  public function getCache($cache_key) {
    return $this->cache($cache_key);
  }

  /**
   * Set the cache for the given cache id.
   *
   * This will also automatically set the cache tags for the current query. The
   * base implementation of this class just takes the placeholders and
   * transforms them into cache tags.
   *
   * @param string $cache_key
   *   The cache key.
   * @param mixed $data
   *   The data to store for the cache key.
   */
  public function setCache($cache_key, $data) {
    $this->cache($cache_key, $data, FALSE, NULL, $this->getCacheTags());
  }

  /**
   * Retrieve the base types from the API.
   */
  private function fetchBaseTypes(): void {
    if ($this->baseTypes !== NULL) {
      return;
    }
    $payload = "
      {
        categoryTypes {
          items {
            Id
            Name
          }
        }
        entityTypes {
          items {
            Id
            Name
          }
        }
        relationshipTypes {
          items {
            Id
            Name
          }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $this->baseTypes = [
      'category' => array_map(fn($item): CategoryType => new CategoryType($item), $data->categoryTypes->items),
      'entity' => array_map(fn($item): EntityType => new EntityType($item), $data->entityTypes->items),
      'relationship' => array_map(fn($item): RelationshipType => new RelationshipType($item), $data->relationshipTypes->items),
    ];
  }

  /**
   * Retrieve the plan years from the API.
   */
  protected function fetchPlanYears(): void {
    if ($this->planYears !== NULL) {
      return;
    }
    $payload = "
      {
        periods (filter: { PeriodType: { eq: \"Year\" } } ){
          items {
            Id
            CalendarYear
          }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $items = $data->periods->items;
    $ids = array_map(fn($item) => $item->Id, $items);
    $items = array_combine($ids, $items);
    $this->planYears = array_map(fn($item): PlanYear => new PlanYear($item), $items);
  }

  /**
   * Get the items for the given category name.
   *
   * @param string $name
   *   The category name as used in the fabric warehouse.
   *
   * @return array
   *   An array of objects.
   */
  public function getCategoryItems($name): array {
    $category = $this->getCategoryTypeByName($name);
    $payload = "
      {
        categories (filter: {
          Id: {
            eq: {$category->id()}
          }
      }) {
          items {
            Id
            Name
            Description
          }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $items = $data->categories->items ?? [];
    $ids = array_map(fn($item) => $item->Id, $items);
    return array_combine($ids, $items);
  }

  /**
   * Get relationship items.
   *
   * @param int $source_type_id
   *   The id of the source entity type.
   * @param int $target_type_id
   *   The id of the target entity type.
   * @param int $source_id
   *   The id of the source entity.
   * @param int $target_id
   *   The id of the target entity.
   *
   * @return array
   *   An array of objects.
   */
  public function getRelationshipItems(int $source_type_id, int $target_type_id, ?int $source_id = NULL, ?int $target_id = NULL) {
    $filters = [
      "FromEntityTypeId: { eq: {$source_type_id} }",
      "ToEntityTypeId: { eq: {$target_type_id} }",
    ];
    if ($source_id !== NULL) {
      $filters[] = "FromId: { eq: {$source_id} }";
    }
    if ($target_id !== NULL) {
      $filters[] = "ToId: { eq: {$target_id} }";
    }
    $payload = "
      {
        relationships (filter: { " . implode('', $filters) . " }) {
          items {
            Id
            RelationshipTypeId
            FromEntityTypeId
            FromId
            ToEntityTypeId
            ToId
          }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    return $data->relationships->items;
  }

  /**
   * Get the available category types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\CategoryType[]
   *   The category types.
   */
  public function getCategoryTypes() {
    $this->fetchBaseTypes();
    return $this->baseTypes['category'];
  }

  /**
   * Get the available entity types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\EntityType[]
   *   The entity types.
   */
  public function getEntityTypes() {
    $this->fetchBaseTypes();
    return $this->baseTypes['entity'];
  }

  /**
   * Get the available relationship types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\RelationshipType[]
   *   The relationship types.
   */
  public function getRelationshipTypes() {
    $this->fetchBaseTypes();
    return $this->baseTypes['relationship'];
  }

  /**
   * Get a category type by its name.
   *
   * @param string $name
   *   The name of the category type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\CategoryType|null
   *   The category type or NULL if not found.
   */
  public function getCategoryTypeByName(string $name): ?CategoryType {
    foreach ($this->getCategoryTypes() as $type) {
      if ($type->getName() === $name) {
        return $type;
      }
    }
    return NULL;
  }

  /**
   * Get an entity type by its name.
   *
   * @param string $name
   *   The name of the entity type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\EntityType|null
   *   The entity type or NULL if not found.
   */
  public function getEntityTypeByName(string $name): ?EntityType {
    foreach ($this->getEntityTypes() as $type) {
      if ($type->getName() === $name) {
        return $type;
      }
    }
    return NULL;
  }

  /**
   * Get a relationship type by its name.
   *
   * @param string $name
   *   The name of the relationship type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\RelationshipType|null
   *   The relationship type or NULL if not found.
   */
  public function getRelationshipTypeByName(string $name): ?RelationshipType {
    foreach ($this->getRelationshipTypes() as $type) {
      if ($type->getName() === $name) {
        return $type;
      }
    }
    return NULL;
  }

}
