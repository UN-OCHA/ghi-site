<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Interface for API objects that hold a namespace reference.
 */
interface ApiObjectNamespaceInterface {

  /**
   * Set the API namespace.
   *
   * @param string $namespace
   *   The namespace.
   */
  public function setNamespace(string $namespace);

  /**
   * Get the API namespace.
   *
   * @return string
   *   The namespace.
   */
  public function getNamespace();

}
