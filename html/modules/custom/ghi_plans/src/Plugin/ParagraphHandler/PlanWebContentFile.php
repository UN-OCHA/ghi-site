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
 *   id = "plan_web_content_file",
 *   label = @Translation("Plan web content file")
 * )
 */
class PlanWebContentFile extends ParagraphHandlerBase {
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
        '#theme' => 'image',
        '#uri' => $attachment->url,
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
    $attachments = $q->getPlanWebContentAttachments($plan_id);

    if (empty($attachments)) {
      return;
    }

    foreach ($attachments as $attachment) {
      $renderer = \Drupal::service('renderer');
      $build = [
        '#theme' => 'image',
        '#uri' => $attachment->url,
        '#attributes' => [
          'style' => 'height: 100px',
        ],
      ];
      $preview_image = $renderer->render($build);

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
          '#markup' => Markup::create($preview_image),
        ],
      ];
    }

    $table_header = [
      'id' => $this->t('Attachment ID'),
      'title' => $this->t('Title'),
      'file_name' => $this->t('File name'),
      'file_url' => $this->t('File URL'),
      'preview' => $this->t('Preview'),
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
      '#options' => $file_options,
      '#default_value' => $attachment_ids,
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no images yet.'),
      '#required' => TRUE,
      '#element_validate' => [[$this, 'validate']],
    ];

    // @see https://www.drupal.org/project/drupal/issues/2820359
    $subform['#element_submit'] = [[$this, 'submit']];
    $subform['paragraph'] = [
      '#type' => 'value',
      '#value' => $this->paragraph,
    ];
  }

  public static function submit(&$element, FormStateInterface $form_state) {
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    /** var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $values['paragraph'];
    unset($values['paragraph']);

    $paragraph->setBehaviorSettings('plan_web_content_file', $values);
    $paragraph->setNeedsSave(TRUE);
    $paragraph->save();
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$build) {
  }

}
