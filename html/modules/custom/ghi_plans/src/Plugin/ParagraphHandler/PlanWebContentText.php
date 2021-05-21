<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\ghi_element_sync\SyncableParagraphInterface;

/**
 * Class Card.
 *
 * @ParagraphHandler(
 *   id = "plan_web_content_text",
 *   label = @Translation("Plan web content text"),
 *   data_sources = {
 *     "data" = {
 *       "service" = "ghi_plans.plan_entities_query"
 *     },
 *   },
 * )
 */
class PlanWebContentText extends PlanBaseClass implements SyncableParagraphInterface {

  /**
   * {@inheritdoc}
   */
  const KEY = 'plan_web_content_text';

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config) {
    return [
      'attachment_ids' => $config->attachment_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSourceElementKey() {
    return 'plan_webcontent_file';
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    parent::preprocess($variables, $element);

    // Retrieve the attachments.
    $attachments = $this->getQueryHandler()->getWebContentTextAttachments($this->parentEntity);
    if (empty($attachments)) {
      return;
    }

    $config = $this->getConfig();
    $attachment_ids = $config['attachment_ids'] ?? [];
    if (!is_array($attachment_ids)) {
      $attachment_ids = [$attachment_ids];
    }

    foreach ($attachments as $attachment) {
      if (!in_array($attachment->id, $attachment_ids)) {
        continue;
      }

      $variables['content'][] = [
        '#theme' => 'markup',
        '#markup' => '<div class="plan-webcontent-text">' . $attachment->content . '</div>',
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(&$element, &$form_state, $context) {
    parent::widgetAlter($element, $form_state, $context);

    // Retrieve the attachments.
    $attachments = $this->getQueryHandler()->getWebContentTextAttachments($this->parentEntity);
    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $table_rows[$attachment->id] = [
          'id' => $attachment->id,
          'title' => $attachment->title,
          'content' => $attachment->content,
        ];
      }
    }

    $table_header = [
      'id' => $this->t('Attachment ID'),
      'title' => $this->t('Title'),
      'content' => $this->t('Preview'),
    ];

    $config = $this->getConfig();
    $attachment_ids = $config['attachment_ids'] ?? [];

    $subform = &$element['subform'];
    $subform['attachment_ids'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => FALSE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $table_rows,
      '#default_value' => $attachment_ids,
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no attachments yet.'),
      '#required' => TRUE,
    ];
  }

}
