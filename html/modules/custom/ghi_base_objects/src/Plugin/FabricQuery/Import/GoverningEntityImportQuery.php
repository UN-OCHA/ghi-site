<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\ImportQueryInterface;

/**
 * Plugin implementation of the 'governing_entity_import' fabric query.
 */
#[FabricQuery(
  id: 'governing_entity_import',
  label: new TranslatableMarkup('Governing entity import query'),
)]
class GoverningEntityImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    $payload = '
      coordinationEntities (first: 50000, orderBy: { Id: DESC }) {
        items {
          Id
          Name
          PlanId
          HpcEntityPrototypeId
        }
      }';
    $data = $this->fabricQuery->query($payload);
    $governing_entity_items = $this->getItems($data, 'coordinationEntities');
    return $governing_entity_items;
  }

}
