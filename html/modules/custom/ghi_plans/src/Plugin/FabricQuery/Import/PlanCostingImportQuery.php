<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Plugin\FabricQuery\Interfaces\ImportQueryInterface;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

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
