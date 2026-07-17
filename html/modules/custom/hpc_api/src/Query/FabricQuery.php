<?php

namespace Drupal\hpc_api\Query;

use Drupal\Core\Cache\Cache;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Class representing a Fabric GraphQL query.
 */
class FabricQuery implements \Stringable {

  /**
   * The maximum number of values Fabric accepts in a single IN filter.
   */
  const MAX_FILTER_COUNT_ARRAY = 100;

  /**
   * The default limit for queries.
   *
   * Fabric has an internal default limit of 100 records which is too small.
   */
  const DEFAULT_LIMIT = 10000;

  /**
   * The query name.
   *
   * @var string
   */
  private ?string $queryName;

  /**
   * The items to fetch.
   *
   * @var array
   */
  private array $items = [];

  /**
   * The filters to apply.
   *
   * @var array
   */
  private array $filters = [];

  /**
   * The order of the results.
   *
   * @var array|null
   */
  private ?array $orderBy = NULL;

  /**
   * The aggregation object.
   *
   * @var object|null
   */
  private ?object $aggregation = NULL;

  /**
   * Explicit cache tags for the query.
   *
   * @var string[]
   */
  private array $cacheTags = [];

  /**
   * The limit.
   *
   * @var int
   */
  private $limit;

  /**
   * The after value.
   *
   * @var string
   */
  private $after;

  /**
   * Constructs a new fabric client object.
   */
  public function __construct(?string $query_name = NULL, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL) {
    $this->queryName = $query_name;
    $this->limit = $limit ?? self::DEFAULT_LIMIT;
    $this->after = NULL;

    if ($items !== NULL) {
      $this->setItems($items);
    }

    if ($filters !== NULL) {
      $this->setFilters($filters);
    }
  }

  /**
   * Get the query name.
   */
  public function getQueryName() {
    return $this->queryName;
  }

  /**
   * Set the items to fetch.
   *
   * @param array|string $items
   *   The items to fetch, either as an array or a string.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setItems(array|string $items): self {
    if (is_string($items)) {
      $items = explode(' ', trim($items));
      $items = array_filter(array_map(fn ($item) => trim($item), $items));
    }
    $this->items = $items;
    return $this;
  }

  /**
   * Assure that the given key property is part of the requested properties.
   *
   * @param string $property
   *   The property to assure.
   */
  public function assureKeyProperty(string $property) {
    if (!in_array($property, $this->items)) {
      $this->items = array_merge([$property], $this->items);
    }
  }

  /**
   * Set the filters.
   *
   * @param array $filters
   *   The filters as an array.
   *
   * @return self
   *   Returns the client instance for chaining.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the filters are invalid.
   */
  public function setFilters(array $filters): self {
    $this->validateFilters($filters);
    $this->filters = $filters;
    return $this;
  }

  /**
   * Set cache tags for this query.
   *
   * @param string[] $cache_tags
   *   The cache tags.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setCacheTags(array $cache_tags): self {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $cache_tags);
    return $this;
  }

  /**
   * Get cache tags for this query.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(): array {
    return Cache::mergeTags($this->cacheTags, $this->getFilterCacheTags($this->filters));
  }

  /**
   * Set a single filter.
   *
   * @param string $key
   *   The filter key.
   * @param mixed $value
   *   The filter value.
   *
   * @return self
   *   Returns the client instance for chaining.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the filter value is invalid.
   */
  public function setFilter(string $key, $value): self {
    $this->validateFilters([$key => $value]);
    $this->filters[$key] = $value;
    return $this;
  }

  /**
   * Validate the given set of filters.
   *
   * @param array $filters
   *   The filters as an array.
   *
   * @return bool
   *   TRUE if validation passes, otherwise an exception is thrown.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the filters are invalid.
   */
  public function validateFilters(array $filters) {
    foreach ($filters as $value) {
      if (is_array($value)) {
        if (empty($value)) {
          throw new \InvalidArgumentException('Empty arrays are not allowed as filter values. Called in ' . get_called_class());
        }
        else {
          $this->validateFilters($value);
        }
      }
    }
    return TRUE;
  }

  /**
   * Set the limit.
   *
   * @param int $limit
   *   The limit.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setLimit(int $limit): self {
    $this->limit = $limit;
    return $this;
  }

  /**
   * Set after.
   *
   * @param string $after
   *   The after value.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setAfter(string $after): self {
    $this->after = $after;
    return $this;
  }

  /**
   * Set the order by.
   *
   * @param array $order_by
   *   The order by.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setOrderBy(array $order_by): self {
    $this->orderBy = $order_by;
    return $this;
  }

  /**
   * Set the aggregation.
   *
   * @param array|string $group_field
   *   The field or fields by which to group.
   * @param array $aggregations
   *   The aggregations.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setAggregation(array|string $group_field, array $aggregations): self {
    $this->validateAggregations($aggregations);
    $aggregation = (object) [
      'group_field' => $group_field,
      'aggregations' => $aggregations,
    ];
    $this->aggregation = $aggregation;
    return $this;
  }

  /**
   * Validate the given aggregation object.
   *
   * @param array $aggregations
   *   The aggregations array.
   *
   * @return bool
   *   TRUE if validation passes, otherwise an exception is thrown.
   *
   * @throws InvalidArgumentException
   */
  public function validateAggregations(array $aggregations) {
    $allowed_functions = ['avg', 'count', 'max', 'min', 'sum'];
    foreach (array_keys($aggregations) as $aggregate_function) {
      if (!in_array($aggregate_function, $allowed_functions)) {
        throw new \InvalidArgumentException('Invalid aggregate function ' . $aggregate_function . '. Called in ' . get_called_class());
      }
    }
    return TRUE;
  }

  /**
   * Check if the query uses aggregation.
   *
   * @return bool
   *   TRUE if the query uses aggregation, FALSE otherwise.
   */
  public function isAggregated(): bool {
    return !empty($this->aggregation);
  }

  /**
   * Create a string representation of the query.
   *
   * @return string
   *   The query string.
   *
   * @throws \Exception
   */
  public function buildQueryString() {
    if (!$this->queryName) {
      throw new \Exception('No query name is set yet. Call ::create first.');
    }
    $item_string = $this->buildItemString();
    if (empty($item_string) && !$this->isAggregated()) {
      throw new \Exception('No items to query are set yet. Call ::setItems first.');
    }
    if (!empty($item_string) && $this->isAggregated()) {
      throw new \Exception('Cannot request items and aggregate in the same query.');
    }
    $filter_string = $this->buildFilterString();
    $order_string = $this->buildOrderString();

    $arguments = array_filter([
      'first: ' . $this->limit,
      $this->after ? 'after: "' . $this->after . '"' : NULL,
      !empty($filter_string) ? 'filter: { ' . $filter_string . ' }' : NULL,
      !empty($order_string) ? 'orderBy: { ' . $order_string . ' }' : NULL,
    ]);
    if ($this->isAggregated()) {
      $aggregation_string = $this->buildAggregationString();
      return $this->queryName . ' ( ' . implode(', ', $arguments) . ' ) { ' . $aggregation_string . ' }';
    }
    return $this->queryName . ' ( ' . implode(', ', $arguments) . ' ) { items { ' . $item_string . ' } endCursor hasNextPage }';
  }

  /**
   * Create a string representation of the query.
   *
   * @return string
   *   The query string.
   *
   * @throws \Exception
   */
  public function toString() {
    return $this->buildQueryString();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return $this->toString();
  }

  /**
   * Build an item string for graphql.
   *
   * @param array|null $items
   *   Optional array of items for recursion.
   *
   * @return string|null
   *   The fully build item string for the given items.
   */
  private function buildItemString(?array $items = NULL): ?string {
    $items = $items ?? $this->items;
    if (ArrayHelper::all($this->items, 'is_string')) {
      return implode(' ', $this->items);
    }
    $strings = [];
    foreach ($items as $key => $value) {
      if (is_string($value)) {
        $strings[] = $value;
      }
      elseif (is_array($value) && $key != 'filter') {
        $arguments = array_filter([
          !empty($value['filter']) ? 'filter: { ' . $this->buildFilterString($value['filter']) . ' } ' : NULL,
        ]);
        $strings[] = $key . (!empty($arguments) ? (' ( ' . implode(', ', $arguments) . ')') : '') . ' { ' . $this->buildItemString($value) . ' }';
      }
    }
    return !empty($strings) ? implode(' ', $strings) : NULL;
  }

  /**
   * Build a filter string for graphql.
   *
   * @param array|null $filter
   *   Optional array of filters for recursion.
   *
   * @return string|null
   *   The fully build filter string for the given items.
   */
  private function buildFilterString(?array $filter = NULL) {
    $filter = $filter ?? $this->filters;
    $strings = [];
    $chunked_filter_strings = [];
    foreach (($filter ?? []) as $key => $value) {
      if (is_null($value)) {
        $strings[] = $key . ': { isNull: true }';
      }
      elseif (is_string($value) && $value === 'NOT NULL') {
        $strings[] = $key . ': { isNull: false }';
      }
      elseif (is_numeric($value)) {
        $strings[] = $key . ': { eq: ' . $value . ' }';
      }
      elseif (is_string($value)) {
        $strings[] = $key . ': { eq: "' . $value . '" }';
      }
      elseif (is_bool($value)) {
        $strings[] = $key . ': { eq: ' . ($value ? 'true' : 'false') . ' }';
      }
      elseif (is_array($value) && ArrayHelper::all($value, 'is_numeric') && ArrayHelper::all(array_keys($value), 'is_integer')) {
        // All values are numeric and the keys are integers, so this is
        // probably a list of ids.
        if (count($value) > self::MAX_FILTER_COUNT_ARRAY) {
          // Fabric rejects a single IN operator with more than 100 values.
          $chunked_filter_strings[] = $this->buildChunkedFilterString($key, $value, TRUE);
        }
        else {
          $strings[] = $this->buildListFilterString($key, $value, TRUE);
        }
      }
      elseif (is_array($value) && ArrayHelper::all($value, 'is_string') && ArrayHelper::all(array_keys($value), 'is_integer')) {
        // All values are strings and the keys are integers, so this is
        // probably a list of string values.
        if (count($value) > self::MAX_FILTER_COUNT_ARRAY) {
          // Keep string lists on the same chunking path as numeric ID lists.
          $chunked_filter_strings[] = $this->buildChunkedFilterString($key, $value, FALSE);
        }
        else {
          $strings[] = $this->buildListFilterString($key, $value, FALSE);
        }
      }
      elseif (is_array($value)) {
        // Anything else is treated like a sub filter, e.g.:
        // filter -> location relation -> location -> location property.
        $strings[] = $key . ': { ' . $this->buildFilterString($value) . ' }';
      }
    }
    if (count($chunked_filter_strings) == 1) {
      $strings[] = reset($chunked_filter_strings);
    }
    elseif (count($chunked_filter_strings) > 1) {
      // Multiple oversized filters must all apply, so combine their OR groups
      // with AND rather than appending several sibling OR keys.
      $strings[] = 'and: [{' . implode('}, {', $chunked_filter_strings) . '}]';
    }
    return !empty($strings) ? implode(' ', $strings) : NULL;
  }

  /**
   * Build a single IN filter string.
   *
   * @param string $key
   *   The filter key.
   * @param array $values
   *   The filter values.
   * @param bool $numeric
   *   Whether the filter values should be rendered as numbers.
   *
   * @return string
   *   The filter string.
   */
  private function buildListFilterString(string $key, array $values, bool $numeric): string {
    $value_string = $numeric ? implode(',', $values) : '"' . implode('", "', $values) . '"';
    return $key . ': { in: [' . $value_string . '] }';
  }

  /**
   * Build an OR filter string from IN filter chunks.
   *
   * Fabric rejects IN filters with more than 100 values. Wrapping multiple
   * smaller IN filters in OR preserves the same semantics in one query.
   *
   * @param string $key
   *   The filter key.
   * @param array $values
   *   The filter values.
   * @param bool $numeric
   *   Whether the filter values should be rendered as numbers.
   *
   * @return string
   *   The filter string.
   */
  private function buildChunkedFilterString(string $key, array $values, bool $numeric): string {
    $filters = [];
    foreach (array_chunk($values, self::MAX_FILTER_COUNT_ARRAY) as $chunk) {
      $filters[] = '{ ' . $this->buildListFilterString($key, $chunk, $numeric) . ' }';
    }
    return 'or: [' . implode(', ', $filters) . ']';
  }

  /**
   * Build an order string for graphql.
   *
   * @param array|null $order_by
   *   Optional array of order by items.
   *
   * @return string|null
   *   The fully build order string for the given items.
   */
  private function buildOrderString(?array $order_by = NULL) {
    $order_by = $order_by ?? $this->orderBy;
    $strings = [];
    foreach ($order_by ?? [] as $property => $direction) {
      $strings[] = $property . ': ' . $direction;
    }
    return implode(' ', $strings);
  }

  /**
   * Build aggregate string for graphql.
   *
   * @param object|null $aggregation
   *   Optional aggregation object.
   *
   * @return string|null
   *   The fully build aggregation.
   */
  private function buildAggregationString(?object $aggregation = NULL) {
    $aggregation = $aggregation ?? $this->aggregation;
    if (!$aggregation) {
      return NULL;
    }
    $aggregation_strings = [];
    foreach ($aggregation->aggregations ?? [] as $function => $field) {
      $aggregation_strings[] = $function . '(field: ' . $field . ')';
    }
    if (empty($aggregation_strings)) {
      return NULL;
    }
    $group_field = $aggregation->group_field;
    $group_fields = is_array($group_field) ? $group_field : [$group_field];
    $group_fields = array_filter(array_map('trim', $group_fields));
    // Array group fields request the grouped field values back from Fabric.
    // Keep string group fields on the legacy aggregation-only response shape.
    $group_field_argument = is_array($group_field) ? '[' . implode(', ', $group_fields) . ']' : reset($group_fields);
    $field_selection = is_array($group_field) ? ' fields { ' . implode(' ', $group_fields) . ' }' : '';
    return 'groupBy(fields: ' . $group_field_argument . ') {' . $field_selection . ' aggregations { ' . implode(' ', $aggregation_strings) . ' } }';
  }

  /**
   * Build cache tags from filters that identify common remote data entities.
   *
   * @param array $filters
   *   The query filters.
   *
   * @return string[]
   *   The cache tags.
   */
  private function getFilterCacheTags(array $filters): array {
    $cache_tags = [];
    foreach ($filters as $key => $value) {
      if (is_string($key)) {
        $prefixes = $key === 'EntityId' ? $this->getEntityIdCacheTagPrefixes($filters) : array_filter([$this->getFilterCacheTagPrefix($key)]);
        foreach ($prefixes as $prefix) {
          foreach ($this->getNumericFilterValues($value) as $id) {
            $cache_tags = Cache::mergeTags($cache_tags, [$prefix . ':' . $id]);
          }
        }
      }
      if (is_array($value)) {
        $cache_tags = Cache::mergeTags($cache_tags, $this->getFilterCacheTags($value));
      }
    }
    return $cache_tags;
  }

  /**
   * Get cache tag prefixes for an entity id filter.
   *
   * @param array $filters
   *   The query filters containing the entity id.
   *
   * @return string[]
   *   The cache tag prefixes.
   */
  private function getEntityIdCacheTagPrefixes(array $filters): array {
    $prefixes = ['entity_id'];
    $entity_main_types = $this->getScalarFilterValues($filters['EntityMainType'] ?? NULL);
    foreach ($entity_main_types as $entity_main_type) {
      $prefix = match ($entity_main_type) {
        'Plan' => 'plan_id',
        'LogframeEntity' => 'plan_entity_id',
        'CoordinationEntity' => 'governing_entity_id',
        default => NULL,
      };
      if ($prefix) {
        $prefixes[] = $prefix;
      }
    }
    return array_values(array_unique($prefixes));
  }

  /**
   * Get a cache tag prefix for a filter key.
   *
   * @param string $filter_key
   *   The filter key.
   *
   * @return string|null
   *   The cache tag prefix, if one is known.
   */
  private function getFilterCacheTagPrefix(string $filter_key): ?string {
    return match ($filter_key) {
      'PlanId' => 'plan_id',
      'AttachmentId' => 'attachment_id',
      'MeasurementId' => 'measurement_id',
      'Id' => $this->getPrimaryIdCacheTagPrefix(),
      default => NULL,
    };
  }

  /**
   * Get the cache tag prefix for primary id filters on this query namespace.
   *
   * @return string|null
   *   The cache tag prefix, if one is known.
   */
  private function getPrimaryIdCacheTagPrefix(): ?string {
    return match ($this->queryName) {
      'attachments' => 'attachment_id',
      'measurements' => 'measurement_id',
      'plans' => 'plan_id',
      'logframeEntities' => 'entity_id',
      'coordinationEntities' => 'governing_entity_id',
      default => NULL,
    };
  }

  /**
   * Extract positive numeric ids from a filter value.
   *
   * @param mixed $value
   *   The filter value.
   *
   * @return int[]
   *   The numeric ids.
   */
  private function getNumericFilterValues(mixed $value): array {
    $values = array_filter($this->getScalarFilterValues($value), fn ($item) => is_numeric($item) && (int) $item > 0);
    return array_values(array_unique(array_map('intval', $values)));
  }

  /**
   * Extract scalar values from a possibly nested filter value.
   *
   * @param mixed $value
   *   The filter value.
   *
   * @return array
   *   The scalar values.
   */
  private function getScalarFilterValues(mixed $value): array {
    if (is_scalar($value)) {
      return [$value];
    }
    if (!is_array($value)) {
      return [];
    }
    $values = [];
    foreach ($value as $item) {
      $values = array_merge($values, $this->getScalarFilterValues($item));
    }
    return $values;
  }

  /**
   * Execute the current query.
   *
   * @param string $key_property
   *   The property to use as a key.
   *
   * @return false|array|object
   *   The result from the fabric query or FALSE on failure.
   */
  public function execute(string $key_property = 'Id'): false|array|object {
    return $this->getFabricClient()->execute($this, $key_property);
  }

  /**
   * Disable caching.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function disableCache() {
    $this->getFabricClient()->disableCache();
    return $this;
  }

  /**
   * Get the fabric client.
   *
   * @return \Drupal\hpc_api\Query\FabricClient
   *   The fabric client.
   */
  private function getFabricClient() {
    $client = &drupal_static(__FUNCTION__, $this->getFabricClientInstance());
    return $client;
  }

  /**
   * Get a new instance of the fabric client.
   *
   * @return \Drupal\hpc_api\Query\FabricClient
   *   The fabric client.
   */
  private static function getFabricClientInstance() {
    return \Drupal::service('hpc_api.fabric_client');
  }

}
