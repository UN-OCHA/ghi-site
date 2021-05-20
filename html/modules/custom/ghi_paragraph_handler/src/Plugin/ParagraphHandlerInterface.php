<?php

namespace Drupal\ghi_paragraph_handler\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Paragraph handler plugins.
 */
interface ParagraphHandlerInterface extends PluginInspectionInterface {

  /**
   * Perform preprocessing for a paragraph type.
   *
   * @param array $variables
   *   A theme array from preprocessing the paragraph.
   * @param array $element
   *   The render array for the paragraph from the theme layer.
   */
  public function preprocess(array &$variables, array $element);

  /**
   * Perform build layer additions for this paragraph type.
   *
   * @param array $build
   *   The paragraph render array.
   */
  public function build(array &$build);

  /**
   * Alter the widget.
   */
  public function widgetAlter(&$element, &$form_state, $context);

}
