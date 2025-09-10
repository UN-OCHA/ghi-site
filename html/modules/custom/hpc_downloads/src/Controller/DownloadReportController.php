<?php

namespace Drupal\hpc_downloads\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_downloads\DownloadRecord;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller class for a lissting page and a delete callback.
 */
class DownloadReportController extends ControllerBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  private $fileUrlGenerator;

  /**
   * Public constructor.
   */
  public function __construct(FileSystemInterface $file_system, FileUrlGeneratorInterface $file_url_generator) {
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * Build a list of existing import files.
   */
  public function buildFileListPage() {
    $file_system = $this->fileSystem;
    $import_files = $file_system->scanDirectory(HPCDownloadPluginInterface::DOWNLOAD_DIR, '/.*/');
    $header = [
      $this->t('Filename'),
      $this->t('Created'),
      $this->t('Size'),
      $this->t('Has record'),
    ];
    $rows = [];
    if (!empty($import_files)) {
      // Order by descending creation time.
      usort($import_files, function ($a, $b) {
        return filectime($this->getRealPath($b->uri)) - filectime($this->getRealPath($a->uri));
      });
      foreach ($import_files as $file) {
        $filepath = $this->getRealPath($file->uri);
        $filename = urldecode(basename($file->uri));
        $download_record = DownloadRecord::loadRecord(['file_path' => $file->uri]);

        $row = [];
        $row[] = Link::fromTextAndUrl($filename, $this->fileUrlGenerator->generate($file->uri));
        $row[] = date('d.m.Y H:i:s', filectime($filepath));
        $row[] = ByteSizeMarkup::create(filesize($filepath));
        $row[] = $download_record ? $this->t('Yes') : $this->t('No');
        $rows[] = $row;
      }
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No download files found.'),
    ];
  }

  /**
   * Build a list of existing import files.
   */
  public function buildRecordListPage() {
    $records = DownloadRecord::loadRecords();
    ArrayHelper::sortObjectsByProperty($records, 'id', EndpointQuery::SORT_DESC);

    $header = [
      $this->t('ID'),
      $this->t('File'),
      $this->t('URL'),
      $this->t('User'),
      $this->t('Status'),
      $this->t('Started'),
      $this->t('Completed'),
      $this->t('Message'),
      $this->t('Errors'),
    ];

    $rows = [];
    foreach ($records as $record) {
      $filepath = $this->getRealPath($record->file_path);
      $filename = urldecode(basename($record->file_path));
      $file_exists = file_exists($filepath);

      $row = [];
      $row[] = Link::fromTextAndUrl($record->id, Url::fromRoute('hpc_downloads.reports.download_records.view', ['id' => $record->id]));
      $row[] = $file_exists ? Link::fromTextAndUrl($filename, Url::fromUri($this->fileUrlGenerator->generate($record->file_path))) : $record->file_path;
      $row[] = $this->getSourceLink($record->url);
      $row[] = $this->getUserName($record->uid);
      $row[] = $this->getStatusLabel($record->status);
      $row[] = $this->formatDate($record->started);
      $row[] = $this->formatDate($record->completed);
      $row[] = $record->message;
      $row[] = $record->errors;
      $rows[] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No download records found.'),
    ];
  }

  /**
   * Show details about a download record.
   */
  public function viewRecord($id) {
    $record = DownloadRecord::loadRecordById($id);

    if (!$record) {
      throw new NotFoundHttpException();
    }

    $rows = [];
    foreach ($record as $key => $value) {
      $row = [$key];
      switch ($key) {
        case 'status':
          $row[] = $this->getStatusLabel($value);
          break;

        case 'uid':
          $row[] = $this->getUserName($value);
          break;

        case 'url':
          $row[] = $this->getSourceLink($value);
          break;

        case 'started':
        case 'updated':
        case 'completed':
          $row[] = $this->formatDate($value);
          break;

        default:
          if (is_scalar($value)) {
            $row[] = $value;
          }
          elseif (is_array($value)) {
            $row[] = Markup::create('<pre>' . print_r($this->prepareArray($value), TRUE) . '</pre>');
          }
          else {
            $row[] = Markup::create('<pre>' . print_r($value, TRUE) . '</pre>');
          }
      }

      $rows[] = $row;
    }
    return [
      '#type' => 'table',
      '#header' => [],
      '#rows' => $rows,
    ];
  }

  /**
   * Get the real path for the given URI.
   *
   * @param string $uri
   *   The URI for which to retrieve the real path.
   *
   * @return string
   *   The real path.
   */
  private function getRealPath($uri) {
    return $this->fileSystem->realpath($uri);
  }

  /**
   * Get a label for the given status.
   *
   * @param string $status
   *   The status key.
   *
   * @return string
   *   The label for the given status.
   */
  private function getStatusLabel($status) {
    $map = [
      DownloadRecord::STATUS_NEW => $this->t('New'),
      DownloadRecord::STATUS_PENDING => $this->t('Pending'),
      DownloadRecord::STATUS_SUCCESS => $this->t('Success'),
      DownloadRecord::STATUS_ERROR => $this->t('Error'),
      DownloadRecord::STATUS_ABORTED => $this->t('Aborted'),
    ];
    return !empty($map[$status]) ? $map[$status] : '';
  }

  /**
   * Format the timestamp for a download record.
   */
  private function formatDate($timestamp) {
    return $timestamp ? date('d.m.Y H:i:s', $timestamp) : '';
  }

  /**
   * Get the user name for a download record.
   */
  private function getUserName($uid) {
    /** @var \Drupal\user\Entity $user */
    $user = $uid ? $this->entityTypeManager()->getStorage('user')->load($uid) : NULL;
    return $user ? $user->getAccountName() : $this->t('Anonymous');
  }

  /**
   * Get a link to the source page of a download record.
   */
  private function getSourceLink($url) {
    return Link::fromTextAndUrl(Unicode::truncate($url, 30, FALSE, TRUE), Url::fromUserInput($url));
  }

  /**
   * Prepare an array  for output.
   */
  private function prepareArray(array $array) {
    foreach ($array as &$value) {
      if ($value instanceof MarkupInterface) {
        $value = (string) $value;
      }
      elseif (is_array($value)) {
        $value = $this->prepareArray($value);
      }
    }
    return $array;
  }

}
