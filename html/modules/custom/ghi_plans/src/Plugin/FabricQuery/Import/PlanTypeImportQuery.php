<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Plugin\FabricQuery\Interfaces\ImportQueryInterface;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'plan_type_import' fabric query.
 */
#[FabricQuery(
  id: 'plan_type_import',
  label: new TranslatableMarkup('Plan type import query'),
)]
class PlanTypeImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    return $this->getCategoryItems(self::PLAN_TYPE_CATEGORY_NAME);
  }

}
