<?php

namespace Drupal\ghi_paragraph_handler\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for Paragraph handler plugins.
 */
abstract class ParagraphHandlerBase extends PluginBase implements ParagraphHandlerInterface {
  use StringTranslationTrait;

  /**
   * The Paragraph being handled.
   *
   * @var \Drupal\paragraphs\Entity\Paragraph
   */
  protected $paragraph;

  /**
   * The first parent of this paragraph.
   *
   * @var \Drupal\node\Entity\Node|\Drupal\paragraphs\Entity\Paragraph
   */
  protected $parentEntity;

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {}

  /**
   * {@inheritdoc}
   */
  public function build(array &$build) {}

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(&$element, &$form_state, $context) {}

  /**
   * Initialize the plugin by providing a paragraph.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   A Paragraph entity.
   *
   * @return $this
   */
  public function init(Paragraph $paragraph) {
    $this->paragraph = $paragraph;

    // Set parent entity.
    $this->parentEntity = $paragraph->getParentEntity();

    return $this;
  }

  /**
   * Prepare and dispatch the preprocess method.
   *
   * @param Drupal\paragraphs\Entity\Paragraph $paragraph
   *   A pragraph being preprocessed.
   * @param array $variables
   *   A set of variables from the theme layer.
   */
  public function dispatchPreprocess(Paragraph $paragraph, array &$variables) {
    $this->init($paragraph);

    $element = $this->getRenderable($variables);

    if (method_exists($this, 'preprocess')) {
      $this->preprocess($variables, $element);
    }
  }

  /**
   * Get the renderable element.
   *
   * @param array $variables
   *   Variables from the theme layer.
   *
   * @return array
   *   A render array, or an empty array.
   */
  public function getRenderable(array $variables) {
    $name = $this->getRenderElementName();
    return isset($variables['elements'][$name]) ? $variables['elements'][$name] : [];
  }

  /**
   * Get the name of the element that holds this paragraph type's render array.
   *
   * @return mixed|string
   *   The name of this paragraph type's render element.
   */
  public function getRenderElementName() {
    return $this->paragraph->bundle();
  }

  /**
   * Determine whether this paragraph is a child of another paragraph.
   *
   * @return bool
   *   Whether paragraph is a child of another paragraph.
   */
  public function isNested() {
    return $this->parentEntity->getEntityTypeId() !== 'node';
  }

  /**
   * Prepare and dispatch the build method.
   *
   * @param Drupal\paragraphs\Entity\Paragraph $paragraph
   *   A paragraph being rendered.
   * @param array $build
   *   A build array for a paragraph entity.
   */
  public function dispatchBuild(Paragraph $paragraph, array &$build) {
    $this->init($paragraph);

    if (method_exists($this, 'build')) {
      $this->build($build);
    }
  }

  /**
   * Prepare and dispatch the widget alter method.
   */
  public function dispatchWidgetAlter(Paragraph $paragraph, &$element, &$form_state, $context) {
    $this->init($paragraph);

    if (method_exists($this, 'widgetAlter')) {
      $this->widgetAlter($element, $form_state, $context);
    }
  }

}
