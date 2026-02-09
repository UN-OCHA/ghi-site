<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to lookup entity data.
 */
class EntityLookupForm extends BaseLookupForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_lookup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
      '#options' => [
        'plan' => $this->t('Plan'),
        'planEntity' => $this->t('Plan entity'),
        'governingEntity' => $this->t('Governing entity'),
      ],
    ];
    $form['filter']['entity_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Entity ID'),
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $entity_type = $form_state->getValue('entity_type');
    $entity_id = $form_state->getValue('entity_id');
    if (!$entity_id) {
      return $form;
    }

    if ($entity_type == 'plan') {
      $entity = $this->getPlanQuery()?->disableCache()->getPlan($entity_id) ?? NULL;
    }
    else {
      $entities = $this->getPlanEntityQuery()?->disableCache()->getEntities($entity_type, [$entity_id]) ?? [];
      $entity = reset($entities);
    }

    if ($entity) {
      $form['entity_type'] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->t('Entity type: @type', [
          '@type' => get_class($entity),
        ]),
      ];
      $form['entity_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Processed data'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($entity->toArray(), TRUE),
        ],
      ];
      $form['entity_source_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($entity->getRawData(), TRUE),
        ],
      ];

      foreach ($this->getPublicMethodResults($entity) as $method_name => $result) {
        $form[$method_name] = [
          '#type' => 'details',
          '#title' => $method_name,
          'children' => [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => print_r($result, TRUE),
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

}
