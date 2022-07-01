<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\ManagedFileBlockTrait;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'External Widget' block.
 *
 * @Block(
 *  id = "generic_document_links",
 *  admin_label = @Translation("Document links"),
 *  category = @Translation("Generic elements"),
 *  title = false
 * )
 */
class DocumentLinks extends GHIBlockBase implements SyncableBlockInterface {

  use ManagedFileBlockTrait;

  const MAX_ITEMS = 3;
  const MAX_LANGUAGES = 4;

  const TITLE_MAX_LENGTH = 50;

  const THUMBNAIL_DIRECTORY = 'public://content-panes/document-links/';

  const LANGUAGES = [
    'English' => 'English',
    'Français' => 'Français',
    'Español' => 'Español',
    'Russian' => 'Russian',
    'Ukrainian' => 'Ukrainian',
    'العربية' => 'العربية',
    '普通话' => '普通话',
  ];

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $documents = array_map(function ($item) use ($dry_run) {
      $timestamp = mktime(0, 0, 0, $item->date->month, $item->date->day, $item->date->year);
      $item->date = date('Y-m-d', $timestamp);

      // We need to retrieve the file thumbnail from the source, upload it here
      // and store the file id. But only if this is not a dry-run.
      if (!$dry_run && !empty($item->thumbnail_url)) {
        $thumbnail = file_get_contents($item->thumbnail_url);
        $item->thumbnail = NULL;
        if (!empty($thumbnail)) {
          $target_file = self::THUMBNAIL_DIRECTORY . basename($item->thumbnail_url);
          $file = file_save_data($thumbnail, $target_file, FileSystemInterface::EXISTS_REPLACE);
          $item->thumbnail = $file ? $file->id() : NULL;
        }
      }
      $item->thumbnail = (array) $item->thumbnail;
      unset($item->thumbnail_url);

      if (!property_exists($item, 'file_details')) {
        $item->file_details = [
          [
            'language' => array_key_first(self::LANGUAGES),
            'target_url' => $item->target_url,
            'filesize' => $item->filesize,
            'mimetype' => $item->mimetype,
            'filetype' => $item->filetype,
          ],
        ];
        unset($item->language);
        unset($item->target_url);
        unset($item->filesize);
        unset($item->mimetype);
        unset($item->filetype);
      }
      else {
        $item->file_details = array_map(function ($details) {
          return (array) $details;
        }, $item->file_details);
        $item->file_details = (array) $item->file_details;
      }
      return (array) $item;
    }, $config->documents);

    return [
      'label' => '',
      'label_display' => TRUE,
      'hpc' => [
        'documents' => $documents,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    $items = [];
    foreach ($conf['documents'] as $document) {
      if (empty($document['title']) || empty($document['date'])) {
        continue;
      }
      $items[] = [
        '#theme' => 'document_link_box',
        '#document' => $document,
      ];
    }

    // Build the content.
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => [
          'generic-document-links',
          'up-' . count($items),
        ],
      ],
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'documents' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['documents'] = [
      '#tree' => TRUE,
    ];

    $default_documents = $this->getDefaultFormValueFromFormState($form_state, 'documents');
    for ($i = 0; $i < self::MAX_ITEMS; $i++) {
      $default = $default_documents[$i] ?? NULL;

      $form['documents'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Document #@number', ['@number' => $i + 1]),
        '#open' => $i == 0 || !empty($default['title']),
      ];
      $form['documents'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#description' => $this->t('A title for this document. If the title is longer than @max_length characters, it will be truncated.', [
          '@max_length' => self::TITLE_MAX_LENGTH,
        ]),
        '#default_value' => $default['title'] ?? NULL,
      ];
      $form['documents'][$i]['date'] = [
        '#type' => 'date',
        '#title' => $this->t('Date'),
        '#default_value' => $default['date'] ?? NULL,
      ];
      $form['documents'][$i]['thumbnail'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Select image'),
        '#upload_location' => self::THUMBNAIL_DIRECTORY,
        '#upload_validators' => [
          'file_validate_extensions' => ['jpg jpeg png gif'],
        ],
        '#default_value' => $default['thumbnail'] ?? NULL,
      ];

      // We support adding different languages.
      for ($j = 0; $j < self::MAX_LANGUAGES; $j++) {
        $details = $default['file_details'][$j];

        $form['documents'][$i]['file_details'][$j] = [
          '#title' => $this->t('Document details #@number', ['@number' => $j + 1]),
          '#type' => 'details',
          '#open' => $j == 0 || !empty($details['language']),
        ];
        $form['documents'][$i]['file_details'][$j]['language'] = [
          '#type' => 'select',
          '#title' => $this->t('Language'),
          '#description' => $this->t('Select the language of the document'),
          '#options' => ['' => $this->t('Select Language')] + self::LANGUAGES,
          '#default_value' => $details['language'] ?? NULL,
          '#attributes' => [
            'class' => ['document-language-selection'],
          ],
        ];
        $form['documents'][$i]['file_details'][$j]['target_url'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Target URL'),
          '#description' => $this->t('Specify where the document is located'),
          '#default_value' => $details['target_url'] ?? NULL,
          '#maxlength' => 512,
        ];
      }

    }

    return $form;
  }

  /**
   * Validate handler for portlet configuration form.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form_state->get('current_subform'));
    $subform = $form['container'];

    foreach ($values['documents'] as $key => $document) {
      $file_ext_array = [];
      foreach ($document['file_details'] as $index => $value) {
        // Skip if document title and the document details target url is blank.
        if (empty($document['title']) && empty($value['target_url'])) {
          continue;
        }

        // Skip document details where both the language and target url are
        // blank.
        if (!empty($document['title']) && empty($value['language']) && empty($value['target_url'])) {
          continue;
        }

        // Check if the title for a document is not blank.
        if (empty($document['title']) && (!empty($value['language']) || !empty($value['target_url']))) {
          $form_state->setError($subform['documents'][$key]['title'], $this->t('Document #@number: The <em>Title</em> field is required.', [
            '@number' => $key + 1,
          ]));
          continue;
        }

        // Check if the language is not entered but the target url is entered.
        if (!empty($document['title']) && empty($value['language']) && !empty($value['target_url'])) {
          $form_state->setError($subform['documents'][$key]['file_details'][$index]['language'], $this->t('Document #@number: The <em>Language</em> field is required for Document details #@detail_number.', [
            '@number' => $key + 1,
            '@detail_number' => $index + 1,
          ]));
          continue;
        }

        // Check if the target url is not entered but the language is entered.
        if (!empty($document['title']) && empty($value['target_url']) && !empty($value['language'])) {
          $form_state->setError($subform['documents'][$key]['file_details'][$index]['target_url'], $this->t('Document #@number: The <em>Target URL</em> field is required for Document details #@detail_number.', [
            '@number' => $key + 1,
            '@detail_number' => $index + 1,
          ]));
          continue;
        }

        // Check if the file target url is valid.
        $response = NULL;
        try {
          $response = $this->httpClient->head($value['target_url'], ['stream' => TRUE]);
        }
        catch (\Exception $e) {
          // Just fail silently.
        }
        if (!$response || $response->getStatusCode() !== 200) {
          $form_state->setError($subform['documents'][$key]['file_details'][$index]['target_url'], $this->t('Document #@number: Failed to retrieve file information for <em>Target URL</em> for Document details #@detail_number.', [
            '@number' => $key + 1,
            '@detail_number' => $index + 1,
          ]));
          continue;
        }
        $content_length = $response->getHeader('Content-Length') ?? NULL;
        $content_type = $response->getHeader(('Content-Type')) ?? [];

        $file_size = reset($content_length);
        if (empty($file_size)) {
          $form_state->setError($subform['documents'][$key]['file_details'][$index]['target_url'], $this->t('Document #@number: The <em>Target URL</em> field does not seem to contain a valid reference for Document details #@detail_number.', [
            '@number' => $key + 1,
            '@detail_number' => $index + 1,
          ]));
        }
        else {
          $filename = $value['target_url'];
          $ext = pathinfo($filename, PATHINFO_EXTENSION);
          $mime_type = reset($content_type);
          $file_type = end(explode('/', $mime_type));
          if (strlen($file_type) > 4 || strpos($file_type, '.')) {
            // Prevent file types like
            // vnd.openxmlformats-officedocument.spreadsheetml.sheet.
            $file_type = $ext;
          }

          // Check that all files in this document group have the same type.
          if (!empty($file_ext_array) && !in_array($file_type, $file_ext_array)) {
            $form_state->setError($subform['documents'][$key]['file_details'][$index]['target_url'], $this->t('Document #@number: The <em>Target URL</em> must use the same file type for all the Document details.', [
              '@number' => $key + 1,
            ]));
          }

          if (!$ext || !$mime_type) {
            $form_state->setError($subform['documents'][$key]['file_details'][$index]['target_url'], $this->t('Document #@number: The <em>Target URL</em> does not seem to represent a valid document for Document details #@detail_number.', [
              '@number' => $key + 1,
              '@detail_number' => $index + 1,
            ]));
          }

          $document['file_details'][$index]['filesize'] = $file_size;
          $document['file_details'][$index]['mimetype'] = $mime_type;
          $document['file_details'][$index]['filetype'] = $file_type;

          $form_state->setValue([
            $form_state->get('current_subform'),
            'documents',
            $key,
          ], $document);

          $file_ext_array[] = $file_type;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityInterface $entity, $uuid) {
    $files = $this->getFiles();
    if (empty($files)) {
      return;
    }
    $files = $this->getFiles();
    $this->persistFiles($files, $entity, $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(EntityInterface $entity, $uuid) {
    $files = $this->getFiles();
    $this->cleanupFiles($files, $entity, $uuid);
  }

  /**
   * Get the files included in this blocks configuration.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file objects configured for this block.
   */
  private function getFiles() {
    $conf = $this->getBlockConfig();
    $files = [];
    foreach ($conf['documents'] as $item) {
      if (empty($item['thumbnail'])) {
        continue;
      }
      $file = File::load(reset($item['thumbnail']));
      if (!$file) {
        continue;
      }
      $files[$file->id()] = $file;
    }
    return $files;
  }

}
