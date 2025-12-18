<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\ImportQueryInterface;

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
      plans (first: 10000, orderBy: { Id: DESC }) {
        items {
          Id
          Name
          ShortName
        }
      }';
    $data = $this->fabricQuery->query($payload);
    return $this->getItems($data, 'plans');
  }

}
