<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\hpc_api\Helpers\QueryHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller class for a lissting page and a delete callback.
 */
class ImportFileReportController extends ControllerBase {

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  private $fileSystem;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $stack;

  /**
   * Public constructor.
   */
  public function __construct(FileSystem $file_system, RequestStack $stack) {
    $this->fileSystem = $file_system;
    $this->stack = $stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('request_stack'),
    );
  }

  /**
   * Build a list of existing import files.
   */
  public function buildListPage() {
    $import_files = $this->fileSystem->scanDirectory(QueryHelper::IMPORT_DIR, '/.*\.json/');
    $header = [
      $this->t('Filename'),
      $this->t('Created'),
      $this->t('Size'),
      $this->t('Operations'),
    ];
    $rows = [];
    foreach ($import_files as $file) {
      $filepath = $this->fileSystem->realpath($file->uri);
      $filename = urldecode(basename($file->uri));
      $row = [];
      $row[] = $filename;
      $row[] = date('d.m.Y H:i:s', filectime($filepath));
      $row[] = format_size(filesize($filepath));
      $row[] = Link::fromTextAndUrl('delete', URL::fromRoute('hpc_api.reports.import_files.delete', [
        'filename' => basename($filepath),
      ]));
      $rows[] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No import files found.'),
    ];
  }

  /**
   * Delete an file as passed in via $_GET.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response after the file has been deleted.
   */
  public function deleteFile() {
    $filename = $this->stack->getCurrentRequest()->query->get('filename');
    if ($filename) {
      $filepath = $this->fileSystem->realpath(rtrim(QueryHelper::IMPORT_DIR, '/') . '/' . $filename);
      if (file_exists($filepath)) {
        unlink($filepath);
        $this->messenger()->addStatus($this->t(':filename has been deleted.', [
          ':filename' => urldecode($filename),
        ]));
      }
    }
    return $this->redirect('hpc_api.reports.import_files');
  }

}
