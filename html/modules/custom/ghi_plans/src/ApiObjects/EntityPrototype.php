<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API entity prototype objects.
 */
class EntityPrototype extends BaseObject {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    return (object) [
      'id' => $data->id,
      'ref_code' => $data->refCode,
      'type' => $data->type,
      'name_singular' => $data->value->name->en->singular,
      'name_plural' => $data->value->name->en->plural,
      'order_number' => $data->orderNumber,
      'can_support' => $data->value->canSupport ?? [],
      'children' => $data->value->possibleChildren ?? [],
    ];

  }

  /**
   * Get the plural name for the entity prototype.
   *
   * @return string
   *   The plural name.
   */
  public function getPluralName() {
    return $this->name_plural;
  }

  /**
   * Get the type for the entity prototype.
   *
   * @return string
   *   The type of prototype as a string.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get the singular name of this prototype.
   *
   * @return string
   *   The singular name.
   */
  public function getNameSingular() {
    return $this->name_singular;
  }

  /**
   * Get the plural name of this prototype.
   *
   * @return string
   *   The plural name.
   */
  public function getNamePlural() {
    return $this->name_plural;
  }

  /**
   * Whether this entity prototype represents a plan entity.
   *
   * Plan entity in the API sense of the term.
   *
   * @return bool
   *   TRUE if the prototype represents a plan entity, FALSE otherwise.
   */
  public function isPlanEntity() {
    return $this->getType() == 'PE';
  }

  /**
   * Whether this entity prototype represents a governing entity.
   *
   * @return bool
   *   TRUE if the prototype represents a governing entity, FALSE otherwise.
   */
  public function isGoverningEntity() {
    return $this->getType() == 'GVE';
  }

  /**
   * Get the ref code for the entity prototype.
   *
   * @return string
   *   The ref code.
   */
  public function getRefCode() {
    return $this->ref_code;
  }

  /**
   * Get the ids of supported prototypes.
   *
   * @todo Define what "support" means.
   *
   * @return int[]
   *   An array of prototype ids that this entity prototype supports.
   */
  public function getSupportedPrototypeIds() {
    if (empty($this->can_support)) {
      return [];
    }
    $can_support = array_filter($this->can_support, function ($item) {
      // Ignore items that are not objects, it's probably an "xor" thing that
      // we don't want to handle at the moment.
      return is_object($item) && property_exists($item, 'id');
    });
    return array_filter(array_map(function ($item) {
      return $item->id ?? NULL;
    }, $can_support));
  }

  /**
   * Get the ids of children.
   *
   * @todo Define what "children" means.
   *
   * @return int[]
   *   An array of children ids that this entity prototype supports.
   */
  public function getChildrenPrototypeIds() {
    if (empty($this->children)) {
      return [];
    }
    $children = array_filter($this->children, function ($item) {
      // Ignore items that are not objects, it's probably an "xor" thing that
      // we don't want to handle at the moment.
      return is_object($item) && property_exists($item, 'id');
    });
    return array_filter(array_map(function ($item) {
      return $item->id ?? NULL;
    }, $children));
  }

}
