<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to execute arbitrary fabric queries.
 */
class FabricQueryForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'fabric_query_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query'),
      '#rows' => 10,
      '#attributes' => [
        'style' => 'margin-bottom: -2rem;',
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'style' => 'margin-bottom: 2rem;',
      ],
    ];

    $query = $form_state->getValue('query') ?? '';
    if (!empty($query)) {
      $fabric_client = $this->getFabricClient();
      $fabric_client->disableCache();
      $error = NULL;

      $start = microtime(TRUE);
      $result = $fabric_client->query($query, $error);
      $duration = microtime(TRUE) - $start;

      $form['meta'] = [
        '#type' => 'details',
        '#title' => $this->t('Meta'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $this->t('The query took @duration seconds', [
            '@duration' => $duration,
          ]),
        ],
      ];

      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Result'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => empty($error) ? print_r($result, TRUE) : print_r($error, TRUE),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Get the fabric client.
   *
   * @return \Drupal\hpc_api\Query\FabricClient
   *   The fabric client.
   */
  public static function getFabricClient() {
    return \Drupal::service('hpc_api.fabric_client');
  }

}
