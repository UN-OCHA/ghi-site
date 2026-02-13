<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Base class for API category objects.
 */
interface CategoryInterface extends ApiObjectInterface, ApiObjectNamespaceInterface {

  /**
   * Get the name of the category.
   *
   * @return string
   *   The name.
   */
  public function getName(): string;

  /**
   * Get the description of the category.
   *
   * @return string|null
   *   The description.
   */
  public function getDescription(): ?string;

  /**
   * Get a UUID for the category.
   *
   * @return string
   *   A UUID, composed of namespace and id.
   */
  public function getUuid(): string;

}
