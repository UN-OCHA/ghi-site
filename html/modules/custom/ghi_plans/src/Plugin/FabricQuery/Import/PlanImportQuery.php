<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Plugin\FabricQuery\Interfaces\ImportQueryInterface;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'plan_import' fabric query.
 */
#[FabricQuery(
  id: 'plan_import',
  label: new TranslatableMarkup('Plan import query'),
)]
class PlanImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    $payload = '
      {
        plans (first: 10000, orderBy: { HpcId: DESC }) {
          items {
            HpcId
            Name
            ShortName
          }
        }
    }';
    $data = $this->fabricQuery->query($payload);
    return $data->plans->items;
  }

}
