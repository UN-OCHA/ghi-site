<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'governing_entity' fabric query.
 */
#[FabricQuery(
  id: 'governing_entity',
  label: new TranslatableMarkup('Governing entity query'),
)]
class GoverningEntityQuery extends FabricQueryBase {

  /**
   * Get a plan by its id.
   *
   * @param int $governing_entity_id
   *   The governing entity id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity|null
   *   The plan object or NULL if not found.
   */
  public function getGoverningEntity(int $governing_entity_id): ?GoverningEntity {
    // Get the governing entity.
    $payload = "
      coordinationEntities (filter: { Id:  { eq: {$governing_entity_id} } } ) {
        items { " . GoverningEntity::GRAPHQL_DIMENSION_ITEMS . " }
      }
      planFieldClusters (filter: { ClusterId: { eq: {$governing_entity_id} } } ) {
        items {
          PlanId
          PlanName
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $governing_entities_data = $data->coordinationEntities->items[0] ?? NULL;
    if ($governing_entities_data === NULL) {
      return NULL;
    }
    $governing_entities_data->plan = $data->planFieldClusters->items[0] ?? NULL;
    return new GoverningEntity($governing_entities_data);
  }

  /**
   * Lookup a country by name.
   *
   * @param string $name
   *   The country name to look for.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   The country object or NULL.
   */
  protected function lookupCountry(string $name): ?Country {
    return $this->countryQuery()->getCountryByName($name);

  }

  /**
   * Get the country query.
   *
   * @return \Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery
   *   The country query.
   */
  public static function countryQuery(): CountryQuery {
    /** @var \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager */
    $fabric_query_manager = \Drupal::service('plugin.manager.fabric_query_manager');
    return $fabric_query_manager->createInstance('country');
  }

}
