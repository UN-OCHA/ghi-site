<?php

namespace Drupal\ghi_paragraph_handler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Paragraph handler item annotation object.
 *
 * @see \Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerManager
 * @see plugin_api
 *
 * @Annotation
 */
class ParagraphHandler extends Plugin {

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

}
