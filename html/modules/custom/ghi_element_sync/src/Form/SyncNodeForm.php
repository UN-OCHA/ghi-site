<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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

    $form['sync_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync all elements'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form['#node'];
    $this->syncManager->syncNode($node);
  }

}
