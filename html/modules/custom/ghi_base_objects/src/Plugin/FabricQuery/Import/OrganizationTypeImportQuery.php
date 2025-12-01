<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\ImportQueryInterface;

/**
 * Plugin implementation of the 'organization_type_import' fabric query.
 */
#[FabricQuery(
  id: 'organization_type_import',
  label: new TranslatableMarkup('Organization type import query'),
)]
class OrganizationTypeImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    return $this->getCategoryItems(self::CATEGORY_NAME_ORGANISATION_TYPE);
  }

}
