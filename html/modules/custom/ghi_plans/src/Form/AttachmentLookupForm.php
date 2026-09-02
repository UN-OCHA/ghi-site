<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a form to lookup attachment data.
 */
class AttachmentLookupForm extends BaseLookupForm implements TrustedCallbackInterface {

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
      $form['attachment_type'] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->t('Attachment type: @type', [
          '@type' => get_class($attachment),
        ]),
      ];
      $form['data'] = [
        '#type' => 'details',
        '#title' => $this->t('Processed data'),
        '#open' => TRUE,
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment->toArray(), TRUE),
        ],
      ];
      $form['source_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($attachment->getRawData(), TRUE),
        ],
      ];
      $form['source_data_json'] = [
        '#type' => 'details',
        '#title' => $this->t('Source data (JSON)'),
        'children' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => json_encode($attachment->getRawData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ],
      ];

      foreach ($this->getPublicMethods($attachment) as $method_name) {
        $lazy_build = [
          '#lazy_builder' => [
            static::class . '::lazyBuildPublicMethodResult',
            [
              $attachment->id(),
              $method_name,
            ],
          ],
          '#create_placeholder' => TRUE,
        ];
        $form['public_method_' . $method_name] = [
          '#markup' => \Drupal::service('renderer')->render($lazy_build),
        ];
      }
    }

    return $form;
  }

  /**
   * Lazy build the result for a public method on an attachment object.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param string $method_name
   *   The name of the method to call.
   *
   * @return array
   *   A render array.
   */
  public static function lazyBuildPublicMethodResult(int $attachment_id, string $method_name) {
    $attachment = self::getAttachmentQuery()?->getAttachment($attachment_id);
    $result = self::getPublicMethodResult($attachment, $method_name);
    return [
      '#type' => 'details',
      '#title' => $method_name,
      'children' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => empty($result) && $result !== 0 && $result !== FALSE ? 'no result' : print_r($result, TRUE),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'lazyBuildPublicMethodResult',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

}
