<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a 'PlanWebcontentFile' block.
 */
#[Block(
  id: 'plan_webcontent_file',
  admin_label: new TranslatableMarkup('Web Content File'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ]
)]
class PlanWebcontentFile extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      usesTitle: FALSE,
      dataSources: [
        'entities' => 'hpc_api:plan_entities_query',
        'attachment' => 'hpc_api:attachment_query',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the attachments.
    $conf = $this->getBlockConfig();
    if (empty($conf['attachment_id'])) {
      return;
    }

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment $attachment */
    $attachment = $query->getAttachment($conf['attachment_id']);
    return [
      '#theme' => 'ghi_image',
      '#url' => $attachment->getUrl(),
      '#credit' => $attachment->getCredit(),
      '#style' => 'wide',
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'attachment_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Retrieve the attachments.
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    $attachments = $this->getCurrentPlanObject() ? $query->getWebContentFileAttachments($this->getCurrentPlanObject()) : NULL;

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $options[$attachment->id] = [
          'id' => $attachment->id,
          'title' => $attachment->title,
          'file_name' => $attachment->file_name,
          'file_url' => Link::fromTextAndUrl($attachment->url, Url::fromUri($attachment->url, [
            'external' => TRUE,
            'attributes' => [
              'target' => '_blank',
            ],
          ])),
          'preview' => [
            'data' => [
              '#theme' => 'imagecache_external',
              '#style_name' => 'thumbnail',
              '#uri' => $attachment->url,
            ],
          ],
        ];
      }
    }

    $table_header = [
      'id' => $this->t('Attachment ID'),
      'title' => $this->t('Title'),
      'file_name' => $this->t('File name'),
      'file_url' => $this->t('File URL'),
      'preview' => $this->t('Preview'),
    ];

    $form['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'attachment_id') ?? array_key_first($options),
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no file attachments yet.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
