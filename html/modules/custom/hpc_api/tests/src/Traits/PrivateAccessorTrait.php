<?php

namespace Drupal\Tests\hpc_api\Traits;

/**
 * Trait for test private methods.
 */
trait PrivateAccessorTrait {

  /**
   * Call a private or protected method on the given class.
   *
   * @param object $class
   *   The object.
   * @param string $method_name
   *   The method name.
   * @param array $arguments
   *   Optional arguments for the method.
   *
   * @return mixed
   *   The return of the method call.
   */
  protected function callPrivateMethod($class, $method_name, $arguments = NULL) {
    // Make the private method callable.
    $method = (new \ReflectionClass($class::class))->getMethod($method_name);
    return $arguments ? $method->invokeArgs($class, $arguments) : $method->invoke($class);
  }

  /**
   * Set a private or protected property on the given class.
   *
   * @param object $class
   *   The object.
   * @param string $property_name
   *   The property name.
   * @param mixed $value
   *   The value to set.
   */
  protected function setPrivateProperty($class, $property_name, $value) {
    // Make the private method callable.
    $property = (new \ReflectionClass($class::class))->getProperty($property_name);
    $property->setValue($class, $value);
  }

  /**
   * Get a private or protected property on the given class.
   *
   * @param object $class
   *   The object.
   * @param string $property_name
   *   The property name.
   *
   * @return mixed
   *   The value.
   */
  protected function getPrivateProperty($class, $property_name) {
    // Make the private method callable.
    $property = (new \ReflectionClass($class::class))->getProperty($property_name);
    return $property->getValue($class);
  }

}
