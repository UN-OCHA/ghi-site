<?php

namespace Drupal\ghi_paragraph_handler\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

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

  /**
   * Get the paragraph entity.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph entity for this plugin.
   */
  public function getParagraph();

  /**
   * Return behavior settings.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An optional form state interface if temporary values should be retrieved
   *   from the current configuration form.
   *
   * @return array
   *   A configuration array, specific to the type of paragraph being edited.
   */
  public function getConfig(FormStateInterface $form_state = NULL);

  /**
   * Set the config for this paragraph handler.
   *
   * Internally sets the config as behavior settings under this plugins config
   * key.
   *
   * @param array $config
   *   The new config to replace the old one.
   */
  public function setConfig(array $config);

}
