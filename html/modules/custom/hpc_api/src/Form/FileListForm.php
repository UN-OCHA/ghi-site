<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hpc_api\Controller\BaseFileReportController;
use Drupal\hpc_api\Traits\BulkFormTrait;

/**
 * Provides a form for managing imported files.
 */
class FileListForm extends FormBase {

  use BulkFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // Have "views_form" in here will improve the styling of this form if the
    // GIN theme is used. See gin_form_alter().
    return 'hpc_api_file_list_views_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, BaseFileReportController $controller = NULL) {
    $form_state->set('file_report_controller', $controller);
    $files = $controller->getFiles();
    $header = [
      'filename' => $this->t('Filename'),
      'date' => $this->t('Created'),
      'size' => $this->t('Size'),
    ];
    $rows = [];
    foreach ($files as $file) {
      $filepath = $controller->fileSystem->realpath($file->uri);
      $filename = urldecode(basename($file->uri));
      $row = [];
      $row['filename'] = $filename;
      $row['date'] = date('d.m.Y H:i:s', filectime($filepath));
      $row['size'] = format_size(filesize($filepath));
      $rows[$filename] = $row;
    }

    $form['table_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Local files'),
    ];
    $form['file_list'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No files found.'),
    ];

    if (!empty($rows)) {
      $this->buildBulkForm($form, $this->getBulkFormActions());
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] != 'bulk_submit') {
      return;
    }
    $action = $form_state->getValue('action');
    if (!array_key_exists($action, $this->getBulkFormActions())) {
      return;
    }
    /** @var \Drupal\hpc_api\Controller\BaseFileReportController $controller */
    $controller = $form_state->get('file_report_controller');
    $filenames = array_filter($form_state->getValue('file_list') ?? []);
    foreach ($filenames as $filename) {
      $controller->deleteFile($filename);
      $this->messenger()->addStatus($this->t(':filename has been deleted.', [
        ':filename' => urldecode($filename),
      ]));
    }
  }

  /**
   * Get the bulk form actions.
   *
   * @return array
   *   An array of action key - label pairs.
   */
  private function getBulkFormActions() {
    return [
      'delete' => $this->t('Delete'),
    ];
  }

}
