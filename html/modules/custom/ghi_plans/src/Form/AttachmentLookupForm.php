<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;

/**
 * Provides a form to lookup attachment data.
 */
class AttachmentLookupForm extends BaseLookupForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'attachment_lookup_form';
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
    $form['filter']['attachment_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Attachment ID'),
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $attachment_id = $form_state->getValue('attachment_id');
    if ($attachment_id && $attachment = $this->getAttachmentQuery()?->getAttachment($attachment_id)) {
      if ($attachment instanceof DataAttachment) {
        $attachment->assureDisaggregatedData();
      }
      $form['attachment_type'] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->t('Attachment type: @type', [
          '@type' => get_class($attachment),
        ]),
      ];
      $form['attachment_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Processed data'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment->toArray(), TRUE),
        ],
      ];
      $form['attachment_source_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment->getRawData(), TRUE),
        ],
      ];

      foreach ($this->getPublicMethodResults($attachment) as $method_name => $result) {
        $form[$method_name] = [
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
