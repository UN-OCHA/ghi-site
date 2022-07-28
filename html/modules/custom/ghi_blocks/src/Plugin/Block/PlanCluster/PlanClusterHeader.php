<?php

namespace Drupal\ghi_blocks\Plugin\Block\PlanCluster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a 'PlanClusterHeader' block.
 *
 * @Block(
 *  id = "plan_cluster_header",
 *  admin_label = @Translation("Plan Cluster Header"),
 *  category = @Translation("Plan cluster elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "attachment" = "attachment_query",
 *  },
 *  title = false,
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan_cluster" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "governing_entity" })
 *   }
 * )
 */
class PlanClusterHeader extends GHIBlockBase implements SyncableBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    return [
      'label' => '',
      'label_display' => FALSE,
      'hpc' => [
        'attachment_id' => $config->attachment_id,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the attachments.
    $conf = $this->getBlockConfig();

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $attachment_query */
    $attachment_query = $this->getQueryHandler('attachment');
    $attachment = !empty($conf['attachment_id']) ? $attachment_query->getAttachment($conf['attachment_id']) : NULL;

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $plan_entities_query */
    $plan_entities_query = $this->getQueryHandler('entities');
    $contacts = $plan_entities_query->getContactAttachments($this->getCurrentBaseObject());

    if (!$contacts && !$attachment) {
      return;
    }

    $build = [
      '#type' => 'container',
    ];

    if ($contacts) {
      $build[] = [
        '#theme' => 'plan_cluster_contacts',
        '#contacts' => $contacts,
      ];
    }

    if ($attachment) {
      $build[] = [
        'attachment' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => [
              'cluster-description',
            ],
          ],
          'content' => [
            '#type' => 'markup',
            '#markup' => Markup::create($attachment->content),
          ],
        ],
      ];
    }
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
    $attachments = $this->getCurrentPlanObject() ? $query->getWebContentTextAttachments($this->getCurrentBaseObject()) : NULL;

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $options[$attachment->id] = [
          'id' => $attachment->id,
          'title' => $attachment->title,
          'text' => Markup::create($attachment->content),
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
