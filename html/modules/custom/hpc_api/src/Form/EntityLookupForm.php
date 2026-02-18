<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hpc_api\Traits\FabricQueryTrait;

/**
 * Provides a form to lookup plan entity data.
 */
class EntityLookupForm extends FormBase {

  use FabricQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_lookup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $entity_type = NULL, ?int $entity_id = NULL): array {
    $form['filter'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'style' => 'display: flex; gap: 1rem; flex-wrap: wrap; align-items: anchor-center;',
      ],
    ];
    $form['filter']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getEntityTypeOptions(),
      '#default_value' => $entity_type,
    ];
    $form['filter']['entity_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Entity ID'),
      '#default_value' => $entity_id,
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $entity_query = $this->getEntityQuery();
    $entity_type = $entity_query->getEntityTypeByName($form_state->getValue('entity_type'));
    if (!$entity_type) {
      $form_state->setErrorByName('entity_type', $this->t('Invalid entity type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_query = $this->getEntityQuery();
    $entity_type = $entity_query->getEntityTypeByName($form_state->getValue('entity_type'));
    $entity_id = $form_state->getValue('entity_id');
    $form_state->setRedirectUrl(new Url('hpc_api.reports.fabric.entity_lookup_page', [
      'entity_type_id' => $entity_type->id(),
      'entity_id' => $entity_id,
    ]));
  }

  /**
   * Get the options for the entity type dropdown.
   *
   * @return string[]
   *   An array of entity type options, keys and values are the name of the
   *   entity type.
   */
  private function getEntityTypeOptions(): array {
    $entity_type_options = $this->getEntityQuery()->getEntityQueryOptions();
    return array_combine($entity_type_options, $entity_type_options);
  }

}
