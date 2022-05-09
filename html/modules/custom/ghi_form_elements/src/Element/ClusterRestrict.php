<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * Provides a cluster restrict element.
 *
 * @FormElement("cluster_restrict")
 */
class ClusterRestrict extends FormElement {

  use AjaxElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processClusterRestrict'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderClusterRestrict'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   *
   * @todo Check if this is actually needed.
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== NULL) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    return NULL;
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processClusterRestrict(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $element['type'] = [
      '#type' => 'radios',
      '#options' => [
        'none' => t('No restrictions'),
        'tag_include' => t('Limit to tag'),
        'tag_exclude' => t('Exclude tag'),
      ],
      '#default_value' => !empty($element['#default_value']['type']) ? $element['#default_value']['type'] : 'none',
    ];
    $type_selector = FormElementHelper::getStateSelector($element, ['type']);
    $element['tag'] = [
      '#type' => 'select',
      '#title' => t('Restrict by cluster'),
      '#options' => TaxonomyHelper::getTermOptionsForVocabularyKeyedByField('cluster_tags', 'field_tag'),
      '#default_value' => !empty($element['#default_value']['tag']) ? $element['#default_value']['tag'] : NULL,
      '#states' => [
        'visible' => [
          [
            ':input[name="' . $type_selector . '"]' => ['value' => 'tag_include'],
          ],
          [
            ':input[name="' . $type_selector . '"]' => ['value' => 'tag_exclude'],
          ],
        ],
      ],
      '#weight' => 1,
    ];

    if (!empty($element['#ajax'])) {
      $element['type']['#ajax'] = $element['#ajax'];
      $element['tag']['#ajax'] = $element['#ajax'];
    }

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderClusterRestrict(array $element) {
    $element['#attributes']['type'] = 'cluster_restrict';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-cluster-restrict']);
    return $element;
  }

}
