<?php

namespace Drupal\ghi_form_elements\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a reusable configuration item plugin annotation object.
 *
 * @Annotation
 */
class ConfigurationContainerItem extends Plugin {

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
   * A description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
