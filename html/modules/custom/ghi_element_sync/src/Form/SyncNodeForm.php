<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_element_sync\SyncException;
use Drupal\ghi_element_sync\SyncManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form for syncing page elements of a specific node from a remote source.
 */
class SyncNodeForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\ghi_element_sync\SyncManager
   */
  protected $syncManager;

  /**
   * Public constructor.
   */
  public function __construct(SyncManager $sync_manager) {
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_element_sync.sync_elements'),
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

    $form['sync_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync all elements'),
    ];

    $form['sync_selected'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync selected elements'),
    ];

    $header = [
      'source_type' => $this->t('Source type'),
      'plugin' => $this->t('Plugin'),
      'syncable' => $this->t('Syncable'),
      'status' => $this->t('Status'),
    ];

    $form['sync_element_select'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => [],
    ];

    try {
      foreach ($this->syncManager->getRemoteConfigurations($node) as $element) {
        $is_syncable = $this->syncManager->isSyncable($element);
        $row = [];
        $row['source_type'] = $element->type;
        $definition = $this->syncManager->getCorrespondingPluginDefintionForElement($element);
        $row['plugin'] = $definition ? $definition['admin_label'] : $this->t('Unknown');
        $row['syncable'] = $is_syncable ? $this->t('Syncable') : $this->t('Not syncable');
        $row['status'] = $this->syncManager->getSyncStatus($node, $element);

        $form['sync_element_select']['#options'][$element->uuid] = $row;
        $form['sync_element_select'][$element->uuid] = !$is_syncable ? ['#disabled' => TRUE] : NULL;
      }
    }
    catch (SyncException $e) {
      $this->messenger()->addError($this->t('There was a problem accessing the sync source:<br />@error', [
        '@error' => $e->getMessage(),
      ]));
      $form['sync_elements']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form['#node'];
    $selected_source_uuids = array_filter($form_state->getValue('sync_element_select'));
    $action = end($form_state->getTriggeringElement()['#parents']);
    $this->syncManager->syncNode($node, $action == 'sync_selected' ? $selected_source_uuids : NULL);
  }

}
