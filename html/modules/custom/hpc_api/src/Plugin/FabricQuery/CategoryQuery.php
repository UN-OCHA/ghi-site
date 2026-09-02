<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\ApiObjects\CategoryInterface;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'category' fabric query.
 */
#[FabricQuery(
  id: 'category',
  label: new TranslatableMarkup('Category query'),
)]
class CategoryQuery extends FabricQueryBase {

  /**
   * The categories.
   *
   * @var array|null
   */
  protected $categories = NULL;

  /**
   * Get the base type defintions.
   *
   * @return array
   *   An array mapping the graphql query name to the responsible class.
   */
  public function getCategoryDefinitions(): array {
    return self::CATEGORIES;
  }

  /**
   * Get a single category by type and id.
   *
   * @param string $namespace
   *   The type of category, specified by the query namespace.
   * @param int $id
   *   The id of the category.
   *
   * @return \Drupal\hpc_api\ApiObjects\CategoryInterface|null
   *   A category object or NULL.
   */
  public function getCategory(string $namespace, int $id): ?CategoryInterface {
    $this->fetchCategories();
    return $this->categories[$namespace][$id] ?? NULL;
  }

  /**
   * Get all categories.
   *
   * @return array
   *   An array of arrays, keyed by the query key for the category, the values
   *   are arrays of result CategoryInterface objects.
   */
  public function getCategories(): ?array {
    $this->fetchCategories();
    return $this->categories;
  }

  /**
   * Retrieve all categories from the API.
   */
  private function fetchCategories() {
    if ($this->categories !== NULL) {
      return;
    }
    $categories = $this->getCategoryDefinitions();
    $queries = array_map(fn($key, $class) => $this->fabricClient->createQuery($key, $class::getGraphQlItems()), array_keys($categories), $categories);
    $data = $this->fabricClient->executeMultiple($queries);
    $this->categories = [];
    if ($data === FALSE) {
      return;
    }
    foreach ($categories as $namespace => $class_name) {
      $this->categories[$namespace] = !empty($data[$namespace]) ? $this->buildResultObjects($data[$namespace], $class_name, $namespace) : [];
    }
  }

}
