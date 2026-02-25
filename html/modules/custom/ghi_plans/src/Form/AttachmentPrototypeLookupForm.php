<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to lookup attachment prototype data.
 */
class AttachmentPrototypeLookupForm extends BaseLookupForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'attachment_prototype_lookup_form';
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
    $form['filter']['attachment_prototype_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Attachment prototype ID'),
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $attachment_prototype_id = $form_state->getValue('attachment_prototype_id');
    if ($attachment_prototype_id && $attachment_prototype = $this->getAttachmentPrototypeQuery()?->getPrototype($attachment_prototype_id)) {
      $form['data'] = [
        '#type' => 'details',
        '#title' => $this->t('Processed data'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment_prototype->toArray(), TRUE),
        ],
      ];
      $form['source_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment_prototype->getRawData(), TRUE),
        ],
      ];
      $raw_data = $attachment_prototype->getRawData();
      $raw_data->Value = Json::decode($raw_data->Value);
      $form['source_data_json'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data (JSON)'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => json_encode($raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ],
      ];

      foreach ($this->getPublicMethodResults($attachment_prototype) as $method_name => $result) {
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
