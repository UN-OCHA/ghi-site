<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_base_objects\Traits\FootnotePropertyTrait;
use Drupal\ghi_element_sync\SyncException;
use Drupal\ghi_element_sync\SyncManager;
use Drupal\ghi_plans\Entity\Plan;
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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Public constructor.
   */
  public function __construct(SyncManager $sync_manager, EntityTypeManager $entity_type_manager, EntityFieldManager $entity_field_manager) {
    $this->syncManager = $sync_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_element_sync.sync_elements'),
      $container->get('entity_type.manager'),
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
    $form['#attached']['library'] = ['ghi_element_sync/sync_metadata_form'];

    $remote_hero_image_url = NULL;
    $hero_image_sync_state = NULL;
    try {
      $remote_data = $this->syncManager->getRemoteConfigurations($node);
      $remote_hero_image_url = $this->syncManager->getRemoteHeroImageUrl($node);
      $hero_image_sync_state = $this->syncManager->isHeroImageSynced($node);
    }
    catch (SyncException $e) {
      $this->messenger()->addError($this->t('There was a problem accessing the sync source:<br />@error', [
        '@error' => $e->getMessage(),
      ]));
      $form['sync_elements']['#disabled'] = TRUE;
    }

    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    $base_object_fields = $this->entityFieldManager->getFieldDefinitions($base_object->getEntityTypeId(), $base_object->bundle());
    $metadata = $remote_data->metadata ?? (object) [];
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

    $remote_status = $metadata->status ?? NULL;
    $form['properties']['#rows'][] = [
      'property' => $this->t('Status'),
      'remote_value' => $remote_status === NULL ? $this->t('Unknown') : ($remote_status == 1 ? $this->t('Published') : $this->t('Unpublished')),
      'local_value' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
      'status' => (bool) $remote_status == $node->isPublished() ? $this->t('In sync') : $this->t('Changed'),
    ];

    $form['properties']['#rows'][] = [
      'property' => $this->t('Hero image'),
      'remote_value' => $remote_hero_image_url ? [
        'data' => [
          '#theme' => 'ghi_image',
          '#url' => $remote_hero_image_url,
          '#responsive_image_style' => $this->entityTypeManager->getStorage('responsive_image_style')->load('hero'),
          '#attributes' => [
            'style' => 'width: 100%',
          ],
        ],
      ] : NULL,
      'local_value' => [
        'data' => $node->get('field_hero_image')->view(['label' => 'hidden']),
      ],
      'status' => $hero_image_sync_state === NULL ? $this->t('Unclear') : ($hero_image_sync_state ? $this->t('In sync') : $this->t('Changed')),
    ];

    if ($base_object instanceof Plan) {
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
          if ($remote_value !== NULL && !empty($local_def['callback'])) {
            $remote_value = $local_def['callback']($remote_value);
          }
          $local_value = $base_object->{$local_def['field']}->{$local_def['property']} ?? NULL;

          $field = $base_object_fields[$local_def['field']];
          $is_boolean = $field->getItemDefinition()->getDataType() == 'field_item:boolean';
          $field_settings = $field->getSettings();

          $form['properties']['#rows'][] = [
            'property' => $base_object_fields[$local_def['field']]->getLabel(),
            'remote_value' => $is_boolean ? ($remote_value ? $field_settings['on_label'] : $field_settings['off_label']) : $remote_value,
            'local_value' => $is_boolean ? ($local_value ? $field_settings['on_label'] : $field_settings['off_label']) : $local_value,
            'status' => $remote_value == $local_value ? $this->t('In sync') : $this->t('Changed'),
          ];
        }
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
