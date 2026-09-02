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
   * Get the public methods for the given entity.
   *
   * @param object $entity
   *   An entity object.
   *
   * @return string[]
   *   An array of method names.
   */
  protected static function getPublicMethods(object $entity): array {
    $reflection = new \ReflectionClass($entity);
    $methods = [];
    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      $method_name = $method->getName();
      if (!str_starts_with($method_name, 'get') || $method_name == 'getRawData') {
        continue;
      }
      if ($method->getNumberOfRequiredParameters() > 0) {
        continue;
      }
      $methods[] = $method_name;
    }
    return $methods;
  }

  /**
   * Get the results from all public methods.
   *
   * @param object $entity
   *   An object.
   *
   * @return array
   *   The results.
   */
  protected static function getPublicMethodResults(object $entity) {
    $results = [];
    foreach (self::getPublicMethods($entity) as $method_name) {
      $result = self::getPublicMethodResult($entity, $method_name);
      $results[$method_name] = print_r($result, TRUE);
    }
    return $results;
  }

  /**
   * Get the results from all public methods.
   *
   * @param object $entity
   *   An object.
   * @param string $method_name
   *   The method name.
   *
   * @return mixed
   *   The result.
   */
  protected static function getPublicMethodResult(object $entity, string $method_name) {
    $reflection = new \ReflectionClass($entity);
    $method = $reflection->getMethod($method_name);
    $result = $method->invoke($entity);
    return self::castValue($result);
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
  private static function castValue($value) {
    if (is_object($value) && method_exists($value, 'toArray')) {
      $value = $value->toArray();
    }
    elseif (is_object($value) && $value instanceof \Stringable) {
      $value = (string) $value;
    }
    elseif (is_array($value)) {
      foreach ($value as &$item) {
        $item = self::castValue($item);
      }
    }
    return $value;
  }

}
