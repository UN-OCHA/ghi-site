<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerBase;

/**
 * Class Card.
 *
 * @ParagraphHandler(
 *   id = "plan_web_content_text",
 *   label = @Translation("Plan web content text")
 * )
 */
class PlanWebContentText extends ParagraphHandlerBase {
  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    if ($this->isNested()) {
      $variables['nested_class'] = TRUE;
    }

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $plan_id = $this->parentEntity->field_original_id->value;

    $settings = $this->paragraph->getAllBehaviorSettings();
    $config = $settings[$this->pluginId] ?? [];
    $attachment_ids = $config['attachment_ids'] ?? [];
    if (!is_array($attachment_ids)) {
      $attachment_ids = [$attachment_ids];
    }

    /** @var \Drupal\hpc_api\Query\EndpointPlanQuery $q */
    $q = \Drupal::service('hpc_api.endpoint_plan_query');
    $attachments = $q->getPlanWebContentAttachments($plan_id);

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
  public function widget_alter(&$element, &$form_state, $context) {
    $subform = &$element['subform'];

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

    $settings = $this->paragraph->getAllBehaviorSettings();
    $config = $settings[$this->pluginId] ?? [];
    $attachment_ids = $config['attachment_ids'] ?? [];

    $subform['attachment_ids'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => FALSE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $table_rows,
      '#default_value' => $attachment_ids,
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no images yet.'),
      '#required' => TRUE,
      '#element_validate' => [[$this, 'validate']],
    ];

    // @see https://www.drupal.org/project/drupal/issues/2820359
    $subform['#element_submit'] = [[$this, 'submit']];
  }

  public static function submit(&$element, FormStateInterface $form_state) {
    // Get field name and delta from parents.
    $parents = $element['#parents'];
    $field_name = array_shift($parents);
    $delta = array_shift($parents);

    // Get paragraph from widget state.
    $widget_state = \Drupal\Core\Field\WidgetBase::getWidgetState([], $field_name, $form_state);

    // Get actual values.
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    // Set widget state.
    if ($values && is_array($values)) {
      $widget_state['paragraphs'][$delta]['entity']->setBehaviorSettings('plan_web_content_file', $values);
      $widget_state['paragraphs'][$delta]['entity']->setNeedsSave(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$build) {
  }

}
