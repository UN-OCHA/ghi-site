<?php

namespace Drupal\ghi_plans\ApiObjects\AttachmentPrototype;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction for API attachment prototype objects.
 */
class AttachmentPrototype extends ApiObjectBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $prototype = $this->getRawData();
    return (object) [
      'id' => $prototype->id,
      'name' => $prototype->value->name->en,
      'ref_code' => $prototype->refCode,
      'type' => strtolower($prototype->type),
      'fields' => array_merge(
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->metrics),
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->measureFields ?? [])
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

}
