<?php

namespace Drupal\ghi_blocks\Plugin\Block\PlanCluster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a 'PlanClusterHeader' block.
 */
#[Block(
  id: 'plan_cluster_header',
  admin_label: new TranslatableMarkup('Plan Cluster Header'),
  category: new TranslatableMarkup('Plan cluster elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), constraints: ['Bundle' => 'governing_entity']),
  ],
)]
class PlanClusterHeader extends GHIBlockBase implements MultiStepFormBlockInterface {

  use PlanQueryTrait;

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      usesTitle: FALSE,
      configForms: [
        'attachment' => [
          'title' => 'Attachment',
          'callback' => 'attachmentForm',
          'base_form' => TRUE,
        ],
        'display' => [
          'title' => 'Display',
          'callback' => 'displayForm',
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the attachments.
    $conf = $this->getBlockConfig();

    $base_object = $this->getCurrentBaseObject();
    $contacts = $base_object instanceof GoverningEntity ? $base_object->getSourceObject()?->getContacts() : NULL;

    if (!$contacts) {
      return;
    }

    $build = [
      '#type' => 'container',
    ];

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
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function attachmentForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Retrieve the attachments. Text attachments have been removed, just
    // keeping this here for reference.
    $attachments = NULL;

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
