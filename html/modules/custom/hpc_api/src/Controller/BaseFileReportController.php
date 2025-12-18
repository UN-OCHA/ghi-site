<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->fileSystem = $container->get('file_system');
    $instance->stack = $container->get('request_stack');
    return $instance;
  }

  /**
   * Get the list of currently imported files.
   *
   * @return array
   *   An associative array of objects with 'uri', 'filename', and 'name'
   *   properties corresponding to the matched files.
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
