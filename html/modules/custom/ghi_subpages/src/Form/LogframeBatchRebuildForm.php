<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_subpages\LogframeRebuildBatch;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for batch logframe rebuild.
 */
class LogframeBatchRebuildForm extends FormBase {

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_subpages\LogframeManager
   */
  protected $logframeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->logframeManager = $container->get('ghi_subpages.logframe_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_subpages_logframe_batch_rebuild_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#node'] = $node;
    $form['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The logframe pages will be completely rebuild, removing all existing elements on the page, including ones that have been manually added and configured.<br />There is no extra confirmation before overwriting existing pages.<br /><br />Rebuilding the logframes will take some time. Please do not close this browser window while the rebuilding is in progress.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild all logframe pages now'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setFinishCallback([LogframeRebuildBatch::class, 'finish'])
      ->setTitle($this->t('Rebuilding logframe pages'))
      ->setInitMessage($this->t('Starting process.'))
      ->setErrorMessage($this->t('The rebuilding process has encountered an error.'));

    $batch_builder->addOperation([LogframeRebuildBatch::class, 'process'], [
      $this->logframeManager,
    ]);
    batch_set($batch_builder->toArray());
  }

}
