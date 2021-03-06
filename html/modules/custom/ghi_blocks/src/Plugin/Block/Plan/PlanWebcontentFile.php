<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'PlanWebcontentFile' block.
 *
 * @Block(
 *  id = "plan_webcontent_file",
 *  admin_label = @Translation("Web Content File"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "attachment" = "attachment_query",
 *  },
 *  title = false,
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *   }
 * )
 */
class PlanWebcontentFile extends GHIBlockBase {

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
    $attachment = $query->getAttachment($conf['attachment_id']);
    return [
      '#theme' => 'imagecache_external',
      '#style_name' => 'wide',
      '#uri' => $attachment->url,
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
    $file_options = [];

    // Retrieve the attachments.
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    $attachments = $this->getCurrentPlanObject() ? $query->getWebContentFileAttachments($this->getCurrentPlanObject()) : NULL;

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $file_options[$attachment->id] = [
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
      '#options' => $file_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'attachment_id'),
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no images yet.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
      ],
    ];
    return $form;
  }

}
