<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides an element for selecting from entity previews.
 *
 * @FormElement("entity_preview_select")
 */
class EntityPreviewSelect extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => [],
      '#input' => TRUE,
      '#tree' => TRUE,
      '#entities' => NULL,
      '#entity_type' => NULL,
      '#view_mode' => NULL,
      '#limit_field' => NULL,
      '#allow_featured' => NULL,
      '#process' => [
        [$class, 'processEntityPreviewSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderEntityPreviewSelect'],
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
    if ($input) {
      // Make sure input is returned as normal during item configuration.
      self::massageValues($input, ['order', 'selected', 'featured']);
      return $input;
    }
    return NULL;
  }

  /**
   * Massage the submitted input values from strings to arrays.
   *
   * @param array $input
   *   The input array.
   * @param array $value_keys
   *   The value keys to process.
   */
  public static function massageValues(array &$input, array $value_keys) {
    foreach ($value_keys as $value_key) {
      if (!array_key_exists($value_key, $input) || empty($input[$value_key])) {
        $input[$value_key] = [];
        continue;
      }
      if (is_array($input[$value_key])) {
        continue;
      }
      if (strpos($input[$value_key], ',') === FALSE) {
        $input[$value_key] = (array) $input[$value_key];
        continue;
      }
      $input[$value_key] = array_filter(explode(',', $input[$value_key]));
    }
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processEntityPreviewSelect(array &$element, FormStateInterface $form_state) {

    // Get a name that let's us identify this element.
    $name = Html::getUniqueId(implode('-', array_merge(['edit'], $element['#parents'])));

    $element['#wrapper_attributes']['data-drupal-selector'] = $name;

    $entities = $element['#entities'];
    $entity_type = $element['#entity_type'];
    $view_mode = $element['#view_mode'];
    $allow_featured = !empty($element['#allow_featured']) && is_int($element['#allow_featured']) ? (int) $element['#allow_featured'] : NULL;
    $previews = [];
    foreach ($entities as $entity) {
      $entity_view = \Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($entity, $view_mode);
      $previews[$entity->id()] = \Drupal::service('renderer')->render($entity_view);
    }

    $element['#attached']['library'][] = 'ghi_form_elements/entity_preview_select';
    $element['#attached']['drupalSettings']['entity_preview_select'][$name] = [
      'previews' => $previews,
      'entity_ids' => array_keys($entities),
      'limit_field' => $element['#limit_field'] ?? NULL,
      'allow_featured' => $allow_featured,
    ];
    $element['order'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', array_filter((array) $element['#default_value']['order'])),
      '#attributes' => ['class' => Html::getClass('entities_order')],
    ];
    $element['selected'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', array_filter((array) $element['#default_value']['selected'])),
      '#attributes' => ['class' => Html::getClass('entities_selected')],
    ];
    $element['featured'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', array_filter((array) $element['#default_value']['featured'])),
      '#attributes' => ['class' => Html::getClass('entities_featured')],
    ];

    if (!empty($element['#states'])) {
      // Propagate states logic to the child elements.
      $element['order']['#states'] = $element['#states'];
      $element['selected']['#states'] = $element['#states'];
      $element['featured']['#states'] = $element['#states'];
      unset($element['#states']);
    }

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderEntityPreviewSelect(array $element) {
    $element['#attributes']['type'] = 'entity_preview_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-entity-preview-select']);
    return $element;
  }

}
