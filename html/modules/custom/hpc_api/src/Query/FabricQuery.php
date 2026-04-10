<?php

namespace Drupal\hpc_api\Query;

use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Class representing a Fabric GraphQL query.
 */
class FabricQuery implements \Stringable {

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
   */
  public function setFilters(array $filters): self {
    $this->validateFilters($filters);
    $this->filters = $filters;
    return $this;
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
   * @throws InvalidArgumentException
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
   * @param string $group_field
   *   The field by which to group.
   * @param array $aggregations
   *   The aggregations.
   *
   * @return self
   *   Returns the client instance for chaining.
   */
  public function setAggregation(string $group_field, array $aggregations): self {
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
        $strings[] = $key . ': { in: [' . implode(',', $value) . '] }';
      }
      elseif (is_array($value) && ArrayHelper::all($value, 'is_string') && ArrayHelper::all(array_keys($value), 'is_integer')) {
        // All values are strings and the keys are integers, so this is
        // probably a list of string values.
        $strings[] = $key . ': { in: ["' . implode('", "', $value) . '"] }';
      }
      elseif (is_array($value)) {
        // Anything else is treated like a sub filter, e.g.:
        // filter -> location relation -> location -> location property.
        $strings[] = $key . ': { ' . $this->buildFilterString($value) . ' }';
      }
    }
    return !empty($strings) ? implode(' ', $strings) : NULL;
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
  private function buildAggregationString(?array $aggregation = NULL) {
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
    return 'groupBy(fields: ' . $aggregation->group_field . ') { aggregations { ' . implode(' ', $aggregation_strings) . ' } }';
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
