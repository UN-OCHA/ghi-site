<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides a 'PlanWebcontentFile' block.
 *
 * @Block(
 *  id = "plan_webcontent_file",
 *  admin_label = @Translation("Plan: Web Content File"),
 *  category = @Translation("Plans"),
 *  data_sources = {
 *    "data" = {
 *      "service" = "ghi_plans.plan_entities_query"
 *    },
 *  },
 *  title = false,
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class PlanWebcontentFile extends GHIBlockBase implements SyncableBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config) {
    return [
      'label' => '',
      'label_display' => FALSE,
      'hpc' => [
        'attachment_id' => property_exists($config, 'attachment_id') ? $config->attachment_id : NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the attachments.
    $attachments = $this->getQueryHandler()->getWebContentFileAttachments($this->getPageNode());

    $conf = $this->getBlockConfig();
    if (empty($conf['attachment_id'])) {
      return;
    }

    $attachment_id = $conf['attachment_id'];
    $attachments = array_filter($attachments, function ($object) use ($attachment_id) {
      return $object->id == $attachment_id;
    });

    if (empty($attachments)) {
      return;
    }
    $attachment = reset($attachments);

    return [
      '#theme' => 'image',
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
    $attachments = $this->getQueryHandler()->getWebContentFileAttachments($this->getCurrentPlanNode());

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $preview_image = ThemeHelper::theme('image', [
          '#uri' => $attachment->url,
          '#attributes' => [
            'style' => 'height: 100px',
          ],
        ], TRUE, FALSE);

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
    ];
    return $form;
  }

}
