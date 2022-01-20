<?php

namespace Drupal\hpc_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a reusable endpoint query plugin annotation object.
 *
 * @Annotation
 */
class EndpointQuery extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The endpoint definition of the plugin.
   *
   * An associative array containing the keys:
   *   - public: The endpoint to use for non-logged-in users (required)
   *   - authenticated: The endpoint to use for authenticated users (optional)
   *   - version: The endpoint version to use (optional)
   *   - query: An array if query arguments (optional)
   *
   * @var array
   */
  public $endpoint;

}
