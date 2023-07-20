<?php

namespace Drupal\ghi_sections\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a reusable section menu plugin annotation object.
 *
 * @Annotation
 */
class SectionMenuPlugin extends Plugin {

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

  /**
   * The initial plugin weight.
   *
   * @var int
   */
  public $weight;

}
