<?php

namespace Drupal\hpc_api\Traits;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Provide a logic around object filtering.
 */
trait ObjectFilterTrait {

  /**
   * Filter the given objects based on the given filter array.
   *
   * @param \Drupal\hpc_api\ApiObjects\ApiObjectInterface[] $objects
   *   An array of API objects.
   * @param array $filter
   *   The filter array to apply.
   *
   * @throws InvalidArgumentException
   */
  protected function filterObjects(array &$objects, array $filter) {
    foreach ($filter as $key => $value) {
      if (is_array($value)) {
        $objects = array_filter($objects, fn (ApiObjectInterface $object) => in_array($object->getRawData()->$key, $value));
      }
      elseif (is_scalar($value)) {
        $objects = array_filter($objects, fn (ApiObjectInterface $object) => $object->getRawData()->$key == $value);
      }
      else {
        throw new \InvalidArgumentException('Only scalars and arrays are supported as filter values for requests to the object store.');
      }
      if (empty($objects)) {
        break;
      }
    }
  }

}
