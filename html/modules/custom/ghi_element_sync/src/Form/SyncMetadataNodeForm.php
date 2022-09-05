<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_base_objects\Traits\FootnotePropertyTrait;
use Drupal\ghi_element_sync\SyncException;
use Drupal\ghi_element_sync\SyncManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form for syncing page metadata of a specific node from a remote source.
 */
class SyncMetadataNodeForm extends FormBase {

  use FootnotePropertyTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\ghi_element_sync\SyncManager
   */
  protected $syncManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Public constructor.
   */
  public function __construct(SyncManager $sync_manager, EntityFieldManager $entity_field_manager) {
    $this->syncManager = $sync_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_element_sync.sync_elements'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_element_sync_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#node'] = $node;

    try {
      $remote_data = $this->syncManager->getRemoteConfigurations($node);
    }
    catch (SyncException $e) {
      $this->messenger()->addError($this->t('There was a problem accessing the sync source:<br />@error', [
        '@error' => $e->getMessage(),
      ]));
      $form['sync_elements']['#disabled'] = TRUE;
    }

    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    $base_object_fields = $this->entityFieldManager->getFieldDefinitions($base_object->getEntityTypeId(), $base_object->bundle());
    $metadata = $remote_data->metadata ?? [];
    $header = [
      'property' => $this->t('Property'),
      'remote_value' => $this->t('Remote'),
      'local_value' => $this->t('Local'),
      'status' => $this->t('Status'),
    ];

    $form['properties'] = [
      '#type' => 'table',
      '#header' => $header,
      '#options' => [],
    ];

    $form['properties']['#rows'][] = [
      'property' => $this->t('Status'),
      'remote_value' => $metadata->status == 1 ? $this->t('Published') : $this->t('Unpublished'),
      'local_value' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
      'status' => (bool) $metadata->status == $node->isPublished() ? $this->t('In sync') : $this->t('Changed'),
    ];

    $field_map = $this->syncManager->getMetadataFieldMap($metadata);
    foreach ($field_map as $remote_property => $local_def) {
      if ($remote_property == 'footnotes') {
        foreach ($local_def['properties'] as $footnote_property) {
          $remote_value = $metadata->{$remote_property}->{$footnote_property} ?? NULL;
          $local_value = $this->getFootnoteFromItemList($base_object->{$local_def['field']} ?? NULL, $footnote_property);
          $form['properties']['#rows'][] = [
            'property' => $base_object_fields[$local_def['field']]->getLabel() . ':' . $footnote_property,
            'remote_value' => $remote_value,
            'local_value' => $local_value,
            'status' => $remote_value == $local_value ? $this->t('In sync') : $this->t('Changed'),
          ];
        }
      }
      else {
        $remote_value = $metadata->{$remote_property} ?? NULL;
        $local_value = $base_object->{$local_def['field']}->{$local_def['property']} ?? NULL;
        $form['properties']['#rows'][] = [
          'property' => $base_object_fields[$local_def['field']]->getLabel(),
          'remote_value' => $remote_value,
          'local_value' => $local_value,
          'status' => $remote_value == $local_value ? $this->t('In sync') : $this->t('Changed'),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['sync'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync metadata'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form['#node'];
    $action = end($form_state->getTriggeringElement()['#parents']);
    if ($action == 'sync') {
      $this->syncManager->syncNode($node, NULL, NULL, FALSE, TRUE);
    }
  }

}
