<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\hpc_api\Controller\BaseFileReportController;
use Drupal\hpc_api\Traits\BulkFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing imported files.
 */
class FileListForm extends FormBase {

  use BulkFormTrait;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    return $instance;
  }

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
  public function buildForm(array $form, FormStateInterface $form_state, ?BaseFileReportController $controller = NULL) {
    $form_state->set('file_report_controller', $controller);
    $files = $controller->getFiles();
    $header = [
      'filename' => $this->t('Filename'),
      'date' => $this->t('Created'),
      'size' => $this->t('Size'),
    ];
    $rows = [];

    usort($files, function ($file_1, $file_2) use ($controller) {
      $filepath_1 = $controller->fileSystem->realpath($file_1->uri);
      $filepath_2 = $controller->fileSystem->realpath($file_2->uri);
      return filectime($filepath_2) - filectime($filepath_1);
    });

    foreach ($files as $file) {
      $filepath = $controller->fileSystem->realpath($file->uri);
      $filename = urldecode(basename($file->uri));
      $row = [];
      $row['filename'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $filename,
          '#url' => $this->fileUrlGenerator->generate($file->uri),
        ],
      ];
      $row['date'] = date('d.m.Y H:i:s', filectime($filepath));
      $row['size'] = ByteSizeMarkup::create(filesize($filepath));
      $rows[$filename] = $row;
    }

    $form['file_list'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No files found.'),
    ];

    $this->buildBulkForm($form, $form_state, !empty($rows) ? $this->getBulkFormActions() : []);
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
