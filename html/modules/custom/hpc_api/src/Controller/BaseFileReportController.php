<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller class for a listing files and a delete callback.
 */
abstract class BaseFileReportController extends ControllerBase {

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  public $fileSystem;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $stack;

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
   * Get the list of currently imported files.
   *
   * @return array
   *   Array of file objects.
   */
  abstract public function getFiles();

  /**
   * Get the filepath for the given file name.
   *
   * @param string $filename
   *   The filename for which to get the file path.
   *
   * @return string
   *   The file path.
   */
  abstract public function getFilePath($filename);

  /**
   * Build a list of existing files.
   */
  public function buildListPage() {
    return $this->formBuilder()->getForm('\Drupal\hpc_api\Form\FileListForm', $this);
  }

  /**
   * Delete a file as passed in via $_GET.
   */
  public function deleteFile($filename) {
    $filepath = $this->getFilePath($filename);
    if (file_exists($filepath)) {
      unlink($filepath);
    }
  }

}
