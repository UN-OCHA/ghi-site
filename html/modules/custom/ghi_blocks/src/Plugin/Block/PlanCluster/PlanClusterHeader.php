<?php

namespace Drupal\ghi_blocks\Plugin\Block\PlanCluster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\IncompleteElementConfigurationException;
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
 *  },
 *  config_forms = {
 *    "attachment" = {
 *      "title" = @Translation("Attachment"),
 *      "callback" = "attachmentForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanClusterHeader extends GHIBlockBase implements MultiStepFormBlockInterface, SyncableBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $attachment_id = $config->attachment_id ?? NULL;
    if (empty($attachment_id)) {
      /** @var \Drupal\ghi_plans\Entity\GoverningEntity $current_base_object */
      $current_base_object = BaseObjectHelper::getBaseObjectFromNode($node);
      if ($current_base_object->getPlan()) {
        /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
        $query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('plan_entities_query');
        $query->setPlaceholder('plan_id', $current_base_object->getPlan()->getSourceId());
        $attachments = $query->getWebContentTextAttachments($current_base_object) ?? [];
        $attachment_id = array_key_first($attachments);
      }
    }
    if (empty($attachment_id)) {
      throw new IncompleteElementConfigurationException('Incomplete configuration for "plan_cluster_header"');
    }
    return [
      'label' => '',
      'label_display' => FALSE,
      'hpc' => [
        'attachment' => [
          'attachment_id' => $attachment_id,
        ],
        'display' => [
          'show_email' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the attachments.
    $conf = $this->getBlockConfig();
    $attachment_id = $conf['attachment']['attachment_id'] ?? NULL;

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $attachment_query */
    $attachment_query = $this->getQueryHandler('attachment');
    $attachment = $attachment_id ? $attachment_query->getAttachment($attachment_id) : NULL;

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $plan_entities_query */
    $plan_entities_query = $this->getQueryHandler('entities');
    $contacts = $plan_entities_query->getContactAttachments($this->getCurrentBaseObject());

    if (!$contacts && !$attachment) {
      return;
    }

    $build = [
      '#type' => 'container',
    ];

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

    if ($contacts) {
      $build[] = [
        '#theme' => 'plan_cluster_contacts',
        '#contacts' => $contacts,
        '#show_email' => $conf['display']['show_email'] ?? FALSE,
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
      'attachment' => [
        'attachment_id' => NULL,
      ],
      'display' => [
        'show_email' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'attachment';
  }

  /**
   * {@inheritdoc}
   */
  public function attachmentForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Retrieve the attachments.
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    $attachments = $this->getCurrentPlanObject() ? $query->getWebContentTextAttachments($this->getCurrentBaseObject()) : NULL;

    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $options[$attachment->id] = [
          'title' => $attachment->title,
          'text' => Markup::create($attachment->content),
        ];
      }
    }

    $table_header = [
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

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['show_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show email addresses'),
      '#description' => $this->t('If checked, the email addresses in the contact details will be publicly visible to the whole internet'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'show_email'),
    ];
    return $form;
  }

}
