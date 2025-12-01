<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\ImportQueryInterface;

/**
 * Plugin implementation of the 'plan_costing_import' fabric query.
 */
#[FabricQuery(
  id: 'plan_costing_import',
  label: new TranslatableMarkup('Plan costing import query'),
)]
class PlanCostingImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    return $this->getCategoryItems(self::CATEGORY_NAME_PLAN_COSTING);
  }

}
