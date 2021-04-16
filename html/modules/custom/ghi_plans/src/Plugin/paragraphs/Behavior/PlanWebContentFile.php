<?php

namespace Drupal\ghi_plans\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a way to define margin-bottom spacing for a paragraph.
 *
 * @ParagraphsBehavior(
 *   id = "plan_web_content_file",
 *   label = @Translation("Plan web content file"),
 *   description = @Translation("Plan web content file."),
 *   weight = 0
 * )
 */
class PlanWebContentFile extends ParagraphsBehaviorBase {

  /**
   * Get this plugins Behavior settings.
   *
   * @return array
   *   Behavior settings.
   */
  private function getSettings(ParagraphInterface $paragraph) {
    $settings = $paragraph->getAllBehaviorSettings();
    return $settings[$this->pluginId] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $config = $this->getSettings($paragraph);

    // Paragraph's bottom spacing.
    if (!isset($paragraph->getParentEntity()->field_original_id) || $paragraph->getParentEntity()->field_original_id->isEmpty()) {
      return;
    }

    $plan_id = $paragraph->getParentEntity()->field_original_id->value;

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

    $form['attachment_ids'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => FALSE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $file_options,
      '#default_value' => $config['attachment_ids'] ?? [],
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no images yet.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
  }

}
