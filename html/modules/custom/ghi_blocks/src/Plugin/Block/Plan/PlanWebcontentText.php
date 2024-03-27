<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment;

/**
 * Provides a 'PlanWebcontentText' block.
 *
 * @Block(
 *  id = "plan_webcontent_text",
 *  admin_label = @Translation("Web Content Text"),
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
class PlanWebcontentText extends GHIBlockBase {

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
    if (!$attachment instanceof TextAttachment) {
      return;
    }

    $build = [
      '#type' => 'container',
      'content' => [
        [
          '#type' => 'markup',
          '#markup' => $attachment->getMarkup(),
        ],
      ],
    ];
    return $build;
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
    $attachments = $this->getCurrentPlanObject() ? $query->getWebContentTextAttachments($this->getCurrentPlanObject()) : NULL;

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $options[$attachment->id()] = [
          'id' => $attachment->id(),
          'title' => $attachment->getTitle(),
          'text' => $attachment->getMarkup(),
        ];
      }
    }

    $table_header = [
      'id' => $this->t('Attachment ID'),
      'title' => $this->t('Title'),
      'text' => $this->t('Content preview'),
    ];

    $form['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'attachment_id') ?? array_key_first($options),
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no text attachments yet.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
