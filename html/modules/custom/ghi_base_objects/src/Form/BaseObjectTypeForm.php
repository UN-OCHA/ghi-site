<?php

namespace Drupal\ghi_base_objects\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for base object type entities.
 */
class BaseObjectTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface $base_object_type */
    $base_object_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $base_object_type->label(),
      '#description' => $this->t('Label for the Base object type.'),
      '#required' => TRUE,
    ];

    $form['hasYear'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Has year'),
      '#default_value' => $base_object_type->hasYear(),
      '#description' => $this->t('Check this if the base object type internally supports years (e.g. HPC plans).'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $base_object_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ghi_base_objects\Entity\BaseObjectType::load',
      ],
      '#disabled' => !$base_object_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $base_object_type = $this->entity;
    $status = $base_object_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Base object type.', [
          '%label' => $base_object_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Base object type.', [
          '%label' => $base_object_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($base_object_type->toUrl('collection'));
  }

}
