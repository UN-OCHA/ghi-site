<?php

namespace Drupal\Tests\ghi_blocks\Traits;

/**
 * Trait for test private methods.
 */
trait PrivateMethodTrait {

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

}
