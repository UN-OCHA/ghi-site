<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Provides an abstract lookup form.
 */
abstract class BaseLookupForm extends FormBase {

  use PlanQueryTrait;

  /**
   * Get the results from all public methods.
   *
   * @param object $entity
   *   An object.
   *
   * @return string[]
   *   The results.
   */
  protected function getPublicMethodResults($entity) {
    $reflection = new \ReflectionClass($entity);
    $results = [];
    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      $method_name = $method->getName();
      if (!str_starts_with($method_name, 'get') || $method_name == 'getRawData') {
        continue;
      }
      if ($method->getNumberOfRequiredParameters() > 0) {
        continue;
      }
      $result = $method->invoke($entity);
      $result = $this->castValue($result);
      $results[$method_name] = print_r($result, TRUE);
    }
    return $results;
  }

  /**
   * Cast a value for printing via print_r.
   *
   * @param mixed $value
   *   The input value.
   *
   * @return string|array
   *   The casted value.
   */
  private function castValue($value) {
    if (is_object($value) && method_exists($value, 'toArray')) {
      $value = $value->toArray();
    }
    elseif (is_object($value) && $value instanceof \Stringable) {
      $value = (string) $value;
    }
    elseif (is_array($value)) {
      foreach ($value as &$item) {
        $item = $this->castValue($item);
      }
    }
    return $value;
  }

}
