<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
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

  use PlanQueryTrait;

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
    $queries = [
      $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
        ->setFilter('Id', $governing_entity_id),
      $this->fabricClient->createQuery('planFieldClusters', [
        'PlanId',
        'PlanName',
      ])->setFilter('ClusterId', $governing_entity_id),
    ];
    $data = $this->fabricClient->executeMultiple($queries);
    $governing_entities_data = $data['coordinationEntities'][0] ?? NULL;
    if ($governing_entities_data === NULL) {
      return NULL;
    }
    $governing_entities_data->plan = $data['planFieldClusters'][0] ?? NULL;
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
    return $this->getCountryQuery()->getCountryByName($name);

  }

}
