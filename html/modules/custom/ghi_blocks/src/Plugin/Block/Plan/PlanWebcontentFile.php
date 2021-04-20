<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\Block\SyncableBlockInterface;
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
 *      "arguments" = {
 *        "endpoint" = "public/plan/{plan_id}?content=entities&addPercentageOfTotalTarget=true&version=current",
 *        "api_version" = "v2",
 *      }
 *    }
 *  },
 *  title = false,
 *  field_context_mapping = {
 *    "year" = "field_plan_year",
 *    "plan_id" = "field_original_id"
 *  },
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
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
        'basic' => [
          'attachment_id' => $config->attachment_id,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $data = $this->getData();
    if (empty($data) || empty($data->attachments)) {
      return;
    }

    $conf = $this->getConfiguration();
    if (empty($conf['hpc']['basic']['attachment_id'])) {
      return;
    }

    $attachment_id = $conf['hpc']['basic']['attachment_id'];
    $attachments = array_filter($data->attachments, function ($object) use ($attachment_id) {
      return $object->id == $attachment_id;
    });

    if (empty($attachments)) {
      return;
    }
    $attachment = reset($attachments);

    return [
      '#theme' => 'image',
      '#uri' => $attachment->attachmentVersion->value->file->url,
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    return [
      'hpc' => [
        'basic' => ['attachment_id' => NULL],
      ],
      'label_display' => FALSE,
    ] + parent::baseConfigurationDefaults();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return [
      'basic' => 'basicConfigForm',
    ];
  }

  /**
   * Form builder for the basic config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function basicConfigForm(array $form, FormStateInterface $form_state) {
    $file_options = [];

    $plan_data = $this->getData();
    $attachments = [];
    if (empty($plan_data->attachments)) {
      return $attachments;
    }
    foreach ($plan_data->attachments as $item) {
      if (strtolower($item->type) != strtolower('fileWebContent')) {
        continue;
      }
      $attachments[] = $item;
    }

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        if (empty($attachment->attachmentVersion->value->file->url)) {
          continue;
        }
        $preview_image = ThemeHelper::theme('image', [
          '#uri' => $attachment->attachmentVersion->value->file->url,
          '#attributes' => [
            'style' => 'height: 100px',
          ],
        ], TRUE, FALSE);

        $file_options[$attachment->id] = [
          'id' => $attachment->id,
          'title' => $attachment->attachmentVersion->value->file->title,
          'file_name' => $attachment->attachmentVersion->value->name,
          'file_url' => Link::fromTextAndUrl($attachment->attachmentVersion->value->file->url, Url::fromUri($attachment->attachmentVersion->value->file->url, [
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
      '#required' => FALSE,
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
