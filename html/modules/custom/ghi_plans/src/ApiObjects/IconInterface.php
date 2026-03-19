<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\Core\Url;
use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for objects with icons.
 */
interface IconInterface extends ApiObjectInterface {

  /**
   * Get the url for the icon.
   *
   * @return \Drupal\Core\Url
   *   The url to the icon file.
   */
  public function getIconUrl(): Url;

  /**
   * The embed code for the icon.
   *
   * @return string
   *   The embed code for the icon.
   */
  public function getIconEmbedCode(): string;

}
