<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

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
    $attachment_ids = $config['attachment_ids'];
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
  public function build(array &$build) {
  }

}
