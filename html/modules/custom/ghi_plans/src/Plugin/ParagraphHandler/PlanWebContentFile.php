<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_element_sync\SyncableParagraphInterface;

/**
 * Class Card.
 *
 * @ParagraphHandler(
 *   id = "plan_web_content_file",
 *   label = @Translation("Plan web content file"),
 *   data_sources = {
 *     "data" = {
 *       "service" = "ghi_plans.plan_entities_query"
 *     },
 *   },
 * )
 */
class PlanWebContentFile extends PlanBaseClass implements SyncableParagraphInterface {

  /**
   * {@inheritdoc}
   */
  const KEY = 'plan_web_content_file';

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
    $attachments = $this->getQueryHandler()->getWebContentFileAttachments($this->parentEntity);
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
        '#theme' => 'image',
        '#uri' => $attachment->url,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(&$element, &$form_state, $context) {
    parent::widgetAlter($element, $form_state, $context);

    // Retrieve the attachments.
    $attachments = $this->getQueryHandler()->getWebContentFileAttachments($this->parentEntity);
    if (!empty($attachments)) {
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

        $table_rows[$attachment->id] = [
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
    }

    $table_header = [
      'id' => $this->t('Attachment ID'),
      'title' => $this->t('Title'),
      'file_name' => $this->t('File name'),
      'file_url' => $this->t('File URL'),
      'preview' => $this->t('Preview'),
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
      '#empty' => $this->t('There are no images yet.'),
      '#required' => TRUE,
    ];
  }

}
