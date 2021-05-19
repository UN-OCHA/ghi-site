<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

/**
 * Class Card.
 *
 * @ParagraphHandler(
 *   id = "plan_web_content_text",
 *   label = @Translation("Plan web content text")
 * )
 */
class PlanWebContentText extends PlanBaseClass {

  /**
   * {@inheritdoc}
   */
  const KEY = 'plan_web_content_text';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    parent::preprocess($variables, $element);

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $plan_id = $this->parentEntity->field_original_id->value;

    $config = $this->getConfig();
    $attachment_ids = $config['attachment_ids'] ?? [];
    if (!is_array($attachment_ids)) {
      $attachment_ids = [$attachment_ids];
    }

    /** @var \Drupal\hpc_api\Query\EndpointPlanQuery $q */
    $q = \Drupal::service('hpc_api.endpoint_plan_query');
    $attachments = $q->getPlanWebContentTextAttachments($plan_id);

    if (empty($attachments)) {
      return;
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

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $plan_id = $this->parentEntity->field_original_id->value;

    /** @var \Drupal\hpc_api\Query\EndpointPlanQuery $q */
    $q = \Drupal::service('hpc_api.endpoint_plan_query');
    $attachments = $q->getPlanWebContentTextAttachments($plan_id);

    if (empty($attachments)) {
      return;
    }

    foreach ($attachments as $attachment) {
      $table_rows[$attachment->id] = [
        'id' => $attachment->id,
        'title' => $attachment->title,
        'content' => $attachment->content,
      ];
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
