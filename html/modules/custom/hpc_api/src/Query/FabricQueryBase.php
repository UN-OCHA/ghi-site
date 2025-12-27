<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\ApiObjects\Relationship;
use Drupal\hpc_api\ApiObjects\Types\AgeGroup;
use Drupal\hpc_api\ApiObjects\Types\CategoryType;
use Drupal\hpc_api\ApiObjects\Types\EntityType;
use Drupal\hpc_api\ApiObjects\Types\Gender;
use Drupal\hpc_api\ApiObjects\Types\HealthInterventionCategory;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\ApiObjects\Types\PlanCostingType;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_api\ApiObjects\Types\PlanYear;
use Drupal\hpc_api\ApiObjects\Types\PopulationStatus;
use Drupal\hpc_api\ApiObjects\Types\RelationshipType;
use Drupal\hpc_api\ApiObjects\Types\ResourceType;
use Drupal\hpc_api\ApiObjects\Types\SettlementType;
use Drupal\hpc_api\ApiObjects\Types\Unit;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for endpoint query plugins.
 */
abstract class FabricQueryBase extends PluginBase implements FabricQueryPluginInterface, ContainerFactoryPluginInterface {

  use SimpleCacheTrait;
  use DependencySerializationTrait;

  /**
   * Entity types in Fabric.
   */
  const ENTITY_TYPE_NAME_CATEGORY = 'Category';
  const ENTITY_TYPE_NAME_PERIOD = 'Period';
  const ENTITY_TYPE_NAME_PLAN = 'Plan';

  /**
   * Category types in Fabric.
   */
  const CATEGORY_NAME_ORGANISATION_TYPE = 'OrganizationType';
  const CATEGORY_NAME_PLAN_TYPE = 'PlanType';
  const CATEGORY_NAME_PLAN_COSTING = 'PlanCosting';

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
   * The plan types.
   *
   * @var array|null
   */
  protected $planTypes = NULL;

  /**
   * The plan costing types.
   *
   * @var array|null
   */
  protected $planCostingTypes = NULL;

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
   * Get namespaced items from the given fabric data object.
   *
   * @param object $data
   *   A fabric graphql result object.
   * @param string $namespace
   *   The result namespace.
   * @param string $key_property
   *   The property to use as a key.
   *
   * @return array
   *   The item list.
   */
  protected function getItems(object $data, ?string $namespace = NULL, string $key_property = 'Id'): array {
    if ($namespace === NULL) {
      $properties = array_keys(get_object_vars($data));
      $namespace = count($properties) == 1 ? reset($properties) : NULL;
    }
    return $namespace ? ArrayHelper::keyByProperty($data?->{$namespace}?->items ?? [], $key_property) : [];
  }

  /**
   * Build result objects from the given fabric raw data.
   *
   * @param object $data
   *   A fabric graphql result object.
   * @param string $namespace
   *   The result namespace.
   * @param string $class_name
   *   The name of the class to use when bulding the objects.
   * @param string $key_property
   *   The property to use as a key.
   *
   * @return array
   *   An array of objects.
   */
  protected function buildResultObjectsFromData($data, string $namespace, $class_name, string $key_property = 'Id'): array {
    if (!$data) {
      return [];
    }
    return array_map(fn($item) => new $class_name($item), $this->getItems($data, $namespace, $key_property));
  }

  /**
   * Build result objects from the given fabric result items.
   *
   * @param array $items
   *   An array of fabric result objects.
   * @param string $class_name
   *   The name of the class to use when bulding the objects.
   *
   * @return array
   *   An array of objects.
   */
  protected function buildResultObjects(array $items, $class_name): array {
    return array_map(fn($item) => new $class_name($item), $items);
  }

  /**
   * Get the base type defintions.
   *
   * @return array
   *   An array with the graphql query name as the key and an array with class
   *   name and fetch properties as the value.
   */
  public function getBaseTypeDefinitions(): array {
    // Most of the base types share the same properties.
    $properties = ['Id', 'Name', 'Description'];
    $base_type_definitions = [
      'ageGroups' => [AgeGroup::class, $properties],
      'categoryTypes' => [CategoryType::class, $properties],
      'entityTypes' => [EntityType::class, ['Id', 'Name', 'Alias']],
      'genders' => [Gender::class, $properties],
      'healthInterventionCategories' => [HealthInterventionCategory::class, $properties],
      'metricTypes' => [MetricType::class, $properties],
      'populationStatuses' => [PopulationStatus::class, $properties],
      // 'populationTypes' => [PopulationType::class, $properties],
      'resourceTypes' => [ResourceType::class, $properties],
      'settlementTypes' => [SettlementType::class, $properties],
      'units' => [Unit::class, $properties],
    ];
    ksort($base_type_definitions);
    return $base_type_definitions;
  }

  /**
   * Fetch a single base type from the Fabric backend.
   *
   * @param string $query_key
   *   The query key.
   *
   * @return array|false
   *   An array of result objects, or FALSE on failure.
   */
  public function fetchBaseType($query_key) {
    $queries = $this->getBaseTypeDefinitions();
    $def = $queries[$query_key] ?? NULL;
    if (!$def) {
      return FALSE;
    }

    $payload = $query_key . ' (first: 1000) {
      items { ' . implode(' ', end($def)) . ' }
    }';
    $data = $this->fabricQuery->query($payload);
    $class_name = reset($def);
    return $data ? $this->buildResultObjectsFromData($data, $query_key, $class_name) : FALSE;
  }

  /**
   * Retrieve the base types from the API.
   */
  protected function fetchBaseTypes(): void {
    if ($this->baseTypes !== NULL) {
      return;
    }
    // Get the base type definitions, so we know what to query and what objetct
    // to build.
    $queries = $this->getBaseTypeDefinitions();

    $payloads = array_map(fn($key, $def) => $key . ' (first: 1000) {
      items { ' . implode(' ', end($def)) . ' }
    }', array_keys($queries), $queries);
    $data = $this->fabricQuery->query(implode(' ', $payloads));
    $this->baseTypes = [];
    if ($data === FALSE) {
      return;
    }
    foreach ($queries as $query_key => $def) {
      $class_name = reset($def);
      $this->baseTypes[$query_key] = $this->buildResultObjectsFromData($data, $query_key, $class_name);
    }
  }

  /**
   * Retrieve the plan years from the API.
   */
  protected function fetchPlanYears(): void {
    if ($this->planYears !== NULL) {
      return;
    }
    $payload = "
      periods (filter: { PeriodType: { eq: \"Year\" } } ){
        items {
          Id
          CalendarYear
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $this->planYears = $this->buildResultObjectsFromData($data, 'periods', PlanYear::class);
  }

  /**
   * Retrieve the plan types from the API.
   */
  protected function fetchPlanTypes(): void {
    if ($this->planTypes !== NULL) {
      return;
    }
    $items = $this->getCategoryItems(self::CATEGORY_NAME_PLAN_TYPE);
    $this->planTypes = $this->buildResultObjects($items, PlanType::class);
  }

  /**
   * Retrieve the plan types from the API.
   */
  protected function fetchPlanCostingTypes(): void {
    if ($this->planCostingTypes !== NULL) {
      return;
    }
    $items = $this->getCategoryItems(self::CATEGORY_NAME_PLAN_COSTING);
    $this->planCostingTypes = $this->buildResultObjects($items, PlanCostingType::class);
  }

  /**
   * Get the items for the given category name.
   *
   * @param string $name
   *   The category name as used in the fabric warehouse.
   *
   * @return array
   *   An array of fabric result objects.
   */
  public function getCategoryItems($name): array {
    $category = $this->getCategoryTypeByName($name);
    if (!$category) {
      throw new \Exception('Category ' . $name . ' not found in the Fabric GraphQL API');
    }
    $payload = "
      categories (filter: {
        CategoryTypeId: {
          eq: {$category->id()}
        }
      }) {
        items {
          Id
          ParentId
          Name
          Description
          Code
        }
      }";
    $data = $this->fabricQuery->query($payload);
    return $this->getItems($data, 'categories');
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
   * @return \Drupal\hpc_api\ApiObjects\Relationship[]
   *   An array of objects.
   */
  public function getRelationshipItems(?int $source_type_id, ?int $target_type_id, ?int $source_id = NULL, ?int $target_id = NULL) {
    $filters = array_filter([
      $source_type_id ? "FromEntityTypeId: { eq: {$source_type_id} }" : NULL,
      $target_type_id ? "ToEntityTypeId: { eq: {$target_type_id} }" : NULL,
    ]);
    if ($source_id !== NULL) {
      $filters[] = "FromId: { eq: {$source_id} }";
    }
    if ($target_id !== NULL) {
      $filters[] = "ToId: { eq: {$target_id} }";
    }
    if (empty($filters)) {
      return [];
    }
    $payload = "
      relationships (first: 10000, filter: { " . implode('', $filters) . " }) {
        items {
          Id
          FromEntityTypeId
          FromId
          ToEntityTypeId
          ToId
          RelationshipType
        }
      }";
    $data = $this->fabricQuery->query($payload);
    return $this->buildResultObjectsFromData($data, 'relationships', Relationship::class);
  }

  /**
   * Get the available category types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\CategoryType[]
   *   The category types.
   */
  public function getCategoryTypes(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes['categoryTypes'];
  }

  /**
   * Get the available entity types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\EntityType[]
   *   The entity types.
   */
  public function getEntityTypes(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes['entityTypes'];
  }

  /**
   * Get the available entity types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\EntityType
   *   The entity types.
   */
  public function getEntityTypeById(int $id): EntityType {
    $this->fetchBaseTypes();
    return $this->baseTypes['entityTypes'][$id] ?? NULL;
  }

  /**
   * Get the available relationship types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\RelationshipType[]
   *   The relationship types.
   */
  public function getRelationshipTypes(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes['relationshipTypes'];
  }

  /**
   * Get the available metric types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType[]
   *   The metric types.
   */
  public function getMetricTypes(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes['metricTypes'];
  }

  /**
   * Get a metric type by id.
   *
   * @param int $id
   *   The id of the metric type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType|null
   *   The metric type object or NULL if not found.
   */
  public function getMetricType(int $id): ?MetricType {
    $metric_type = $this->baseTypes['metricTypes'][$id] ?? NULL;
    assert($metric_type instanceof MetricType);
    return $metric_type;
  }

  /**
   * Get the available units.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType[]
   *   The metric types.
   */
  public function getUnits(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes['units'];
  }

  /**
   * Get a unit by id.
   *
   * @param int $id
   *   The id of the unit.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\Unit|null
   *   The unit object or NULL if not found.
   */
  public function getUnit(int $id): ?Unit {
    $unit = $this->baseTypes['units'][$id] ?? NULL;
    assert($unit instanceof Unit);
    return $unit;
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

  /**
   * Get the plan year.
   *
   * @param int $id
   *   The internal id of the plan object.
   *
   * @return int|null
   *   The plan year or NULL.
   */
  protected function getPlanYear(int $id): ?int {
    $this->fetchPlanYears();
    $plan_period_relationships = $this->getPlanPeriodRelationships($id);
    $plan_years = array_filter(array_map(fn($item) => $this->planYears[$item->getTargetId()] ?? NULL, $plan_period_relationships));
    return !empty($plan_years) ? reset($plan_years)->getYear() : NULL;
  }

  /**
   * Get the plan cvategory relationship data for the given id.
   *
   * @param int $id
   *   The internal id of the plan object.
   *
   * @return \Drupal\hpc_api\ApiObjects\Relationship[]
   *   An array of objects.
   */
  protected function getPlanPeriodRelationships(int $id) {
    $plan_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PLAN);
    $period_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PERIOD);
    return $this->getRelationshipItems($plan_entity_type->id(), $period_entity_type->id(), $id);
  }

  /**
   * Get the plan cvategory relationship data for the given id.
   *
   * @param int $id
   *   The internal id of the plan object.
   *
   * @return \Drupal\hpc_api\ApiObjects\Relationship[]
   *   An array of objects.
   */
  protected function getPlanCategoryRelationships(int $id) {
    $category_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_CATEGORY);
    $plan_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PLAN);
    return $this->getRelationshipItems($category_entity_type->id(), $plan_entity_type->id(), NULL, $id);
  }

  /**
   * Lookup the label for an entity.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return string|null
   *   The label if found NULL otherwise.
   */
  public function lookupEntityLabel($entity_type_id, $entity_id): ?string {
    $entity_type = $this->getEntityTypeById($entity_type_id);
    switch ($entity_type->getName()) {
      case 'Plan':
        $data = $this->fabricQuery->query("plans (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'plans') : [];
        return $items[$entity_id]?->Name ?? NULL;

      case 'Project':
        $data = $this->fabricQuery->query("projects (filter: { Id: { eq: {$entity_id} } }) { items { Id ProjectCode Name } } ");
        $items = $data ? $this->getItems($data, 'projects') : [];
        return !empty($items[$entity_id]) ? ($items[$entity_id]->ProjectCode . ': ' . $items[$entity_id]->Name) : NULL;

      case 'Location':
        $data = $this->fabricQuery->query("locations (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'locations') : [];
        return $items[$entity_id]?->Name ?? NULL;

      case 'Organization':
        $data = $this->fabricQuery->query("organizations (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'organizations') : [];
        return $items[$entity_id]?->Name ?? NULL;

      case 'FieldCluster':
        $data = $this->fabricQuery->query("coordinationEntities (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'coordinationEntities') : [];
        return $items[$entity_id]?->Name ?? NULL;

      case 'Period':
        $data = $this->fabricQuery->query("periods (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'periods') : [];
        return $items[$entity_id]?->Name ?? NULL;

      case 'StrategicObjective':
      case 'SpecificObjective':
      case 'ClusterObjective':
      case 'ClusterActivity':
        $data = $this->fabricQuery->query("logframeEntities (filter: { Id: { eq: {$entity_id} } }) { items { Id Name } } ");
        $items = $data ? $this->getItems($data, 'logframeEntities') : [];
        return $items[$entity_id]?->Name ?? NULL;
    }
    return NULL;
  }

}
