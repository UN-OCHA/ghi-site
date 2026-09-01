<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Base class for API type objects.
 */
interface TypeInterface extends ApiObjectInterface {

  /**
   * Get the name of the type.
   *
   * @return string
   *   The name.
   */
  public function getName(): string;

  /**
   * Get the description of the type.
   *
   * @return string|null
   *   The description.
   */
  public function getDescription(): ?string;

}
