<?php

namespace Drupal\hpc_common\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;

/**
 * Base class for custom entity forms, e.g. static blocks, pane comments.
 */
abstract class EntityBlockFormBase extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get the form display id.
   */
  abstract protected function getFormDisplayId();

  /**
   * Add a form element as specified by the given field key.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_key
   *   The field key.
   * @param object $context
   *   The current page context object.
   * @param bool $simple_numeric_widgets
   *   Whether to use simple numeric widgets for multi-value fields. Builds a
   *   single textfield that allows to enter comma-separated values.
   *
   * @return bool
   *   Whether an element has been added or not.
   */
  protected function addFormElementForFieldKey(array &$form = NULL, FormStateInterface $form_state, $field_key, object $context = NULL, $simple_numeric_widgets = FALSE) {
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load($this->getFormDisplayId());
    $form_state->set('form_display', $form_display);

    $entity = $form_state->get('entity');
    $widget = $form_display->getRenderer($field_key);

    if (!$widget) {
      return FALSE;
    }

    $field = $entity->get($field_key);
    $field->filterEmptyItems();

    if ($field->isEmpty() && !empty($context)) {
      $field->setValue($context->hasContextValue() ? $context->getContextValue() : NULL);
    }

    $form['#parents'] = [];
    $form[$field_key] = $widget->form($field, $form, $form_state);
    $form[$field_key]['#access'] = $field->access('edit');

    if ($widget instanceof TextareaWidget) {
      // Hide help link and format select for the text input to unclutter the
      // interface.
      $form[$field_key]['widget'][0]['#description'] = '';
      $form[$field_key]['widget']['#after_build'][] = [
        $this,
        'afterBuildTextareaWidget',
      ];
    }

    if ($widget instanceof OptionsButtonsWidget) {
      $form[$field_key]['widget']['#after_build'][] = [
        $this,
        'afterBuildOptionsButtonsWidget',
      ];
    }

    $field_definition = $entity->get($field_key)->getFieldDefinition();

    if ($simple_numeric_widgets && $field_definition->isMultiple() && $widget instanceof NumberWidget) {
      // Simplify input of multiple-value number fields.
      $default_values = array_filter(array_map(function ($item) {
        return is_array($item) && !empty($item['value']) ? $item['value']['#default_value'] : NULL;
      }, $form[$field_key]['widget']));
      $form[$field_key]['widget']['#access'] = FALSE;
      $form[$field_key . '_simple'] = [
        '#type' => 'textfield',
        '#title' => $form[$field_key]['widget']['#title'],
        '#default_value' => implode(', ', $default_values),
        '#description' => $form[$field_key]['widget']['#description'] . ' ' . $this->t('Enter multiple values separated by comma. Leave empty to apply for all values.'),
      ];
    }

    return TRUE;
  }

  /**
   * Get the submitted value for a given field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_key
   *   The field key.
   * @param object $entity
   *   The entity object, needed to know if the field is multi-value.
   *
   * @return mixed
   *   The submitted value for the field key, as extracted from the form state.
   */
  public function getSubmittedFormValue(array $form, FormStateInterface $form_state, $field_key, $entity) {
    $values = $form_state->getValues();
    $value = $values[$field_key];
    if (!$entity->get($field_key)->getFieldDefinition()->isMultiple()) {
      return $value;
    }
    $value = array_filter($value, function ($item) {
      return is_array($item);
    });
    if (array_key_exists($field_key . '_simple', $values)) {
      // A simplified version of the input widget has been used.
      $value = array_map(function ($item) {
        return [
          'value' => trim($item),
        ];
      }, explode(',', $values[$field_key . '_simple']));
    }
    return $value;
  }

  /**
   * After build callback for textarea widgets.
   *
   * Hide help link and format select for the text input to unclutter the
   * interface.
   *
   * @param array $form_element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form statue object.
   */
  public function afterBuildTextareaWidget(array $form_element, FormStateInterface $form_state) {
    if (isset($form_element[0]['format'])) {
      // All this stuff is needed to hide the help text.
      unset($form_element[0]['format']['guidelines']);
      unset($form_element[0]['format']['help']);
      unset($form_element[0]['format']['#type']);
      unset($form_element[0]['format']['#theme_wrappers']);
      $form_element[0]['format']['format']['#access'] = FALSE;
    }
    return $form_element;
  }

  /**
   * After build callback for option button widgets.
   *
   * @param array $form_element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form statue object.
   */
  public function afterBuildOptionsButtonsWidget(array $form_element, FormStateInterface $form_state) {
    if (!empty($form_element['#locked_options'])) {
      foreach ($form_element['#locked_options'] as $option_key) {
        $form_element[$option_key]['#default_value'] = $option_key;
        $form_element[$option_key]['#value'] = $option_key;
        $form_element[$option_key]['#checked'] = TRUE;
        $form_element[$option_key]['#disabled'] = TRUE;
        $form_element[$option_key]['#attributes']['disabled'] = 'disabled';
      }
    }
    return $form_element;
  }

}
