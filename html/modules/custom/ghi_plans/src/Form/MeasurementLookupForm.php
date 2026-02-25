<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;

/**
 * Provides a form to lookup measurement data.
 */
class MeasurementLookupForm extends BaseLookupForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'measurement_lookup_form';
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
    $form['filter']['measurement_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Measurement ID'),
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $measurement_id = $form_state->getValue('measurement_id');
    if ($measurement_id && $measurement = $this->getMeasurementQuery()?->getMeasurement($measurement_id)) {
      if ($measurement instanceof Measurement) {
        $measurement->assureDisaggregatedData();
      }
      $form['measurement_type'] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->t('measurement type: @type', [
          '@type' => get_class($measurement),
        ]),
      ];
      $form['data'] = [
        '#type' => 'details',
        '#title' => $this->t('Processed data'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($measurement->toArray(), TRUE),
        ],
      ];
      $form['source_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($measurement->getRawData(), TRUE),
        ],
      ];
      $form['source_data_json'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data (JSON)'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => json_encode($measurement->getRawData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ],
      ];

      foreach ($this->getPublicMethodResults($measurement) as $method_name => $result) {
        $form['public_method_' . $method_name] = [
          '#type' => 'details',
          '#title' => $method_name,
          'children' => [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => empty($result) && $result !== 0 && $result !== FALSE ? 'no result' : print_r($result, TRUE),
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
