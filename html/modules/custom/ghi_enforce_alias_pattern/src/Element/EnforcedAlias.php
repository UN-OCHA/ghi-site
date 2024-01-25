<?php

namespace Drupal\ghi_enforce_alias_pattern\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Textfield;

/**
 * Provides an enfoced_alias element.
 *
 * @FormElement("enforced_alias")
 */
class EnforcedAlias extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $info = parent::getInfo();
    $info['#size'] = 0;
    $info['#process'][] = [$class, 'processEnforcedAlias'];
    $info['#pre_render'][] = [$class, 'preRenderEnforcedAlias'];
    $info['#fixed_prefix'] = NULL;
    $info['#original_alias'] = NULL;
    $info['#generated_alias'] = NULL;
    return $info;
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processEnforcedAlias(array &$element, FormStateInterface $form_state) {
    $element['#field_prefix'] = '/' . $element['#fixed_prefix'] . '/';
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderEnforcedAlias(array $element) {
    $element['#attributes']['type'] = 'text';
    Element::setAttributes($element, ['id', 'name', 'value', 'generated_alias', 'fixed_prefix']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-enforced-alias']);
    return $element;
  }

}
