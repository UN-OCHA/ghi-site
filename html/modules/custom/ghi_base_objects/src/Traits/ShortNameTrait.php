<?php

namespace Drupal\ghi_base_objects\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Helper trait for handling shortnames.
 */
trait ShortNameTrait {

  /**
   * Get a shortname for a base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   * @param bool $include_type
   *   Weather to add a type to the name.
   * @param bool $include_year
   *   Weather to add a year to the name.
   *
   * @return string
   *   The shortname for the base object.
   */
  public function getShortName(BaseObjectInterface $base_object, $include_type = FALSE, $include_year = FALSE) {
    $shortname = $base_object->getShortName();
    if (!$shortname) {
      return NULL;
    }

    $type_field_map = [
      'plan' => 'field_plan_type',
    ];
    $type_field = $type_field_map[$base_object->bundle()] ?? NULL;
    if ($include_type && $type_field && $base_object->hasField($type_field) && $type_entity = $base_object->get($type_field)->entity) {
      $shortname .= ' ' . StringHelper::getAbbreviation($type_entity->label());
    }

    if ($include_year && !$base_object->type->needsYear && $base_object->hasField('field_year')) {
      $shortname .= ' ' . $base_object->get('field_year')->value;
    }
    return $shortname;
  }

}
