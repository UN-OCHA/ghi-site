<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'LinkCarousel' block.
 *
 * @Block(
 *  id = "generic_link_carousel",
 *  admin_label = @Translation("Link Carousel"),
 *  category = @Translation("Generic elements"),
 *  title = false
 * )
 */
class LinkCarousel extends GHIBlockBase implements ConfigurableTableBlockInterface {

  use ConfigurationContainerTrait;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected $fileUsage;

  /**
   * The database connection used to store file usage information.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->fileUsage = $container->get('file.usage');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();
    if (empty($conf['items'])) {
      return;
    }

    $context = $this->getBlockContext();
    $carousel_items = [];
    foreach ($conf['items'] as $item) {

      /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem $item_type */
      $item_type = $this->getItemTypePluginForColumn($item, $context);

      $file = $item_type->getImage();
      if (!$file) {
        continue;
      }

      $link = Link::fromTextAndUrl($this->t('Read more'), $item_type->getUrl());
      $link->getUrl()->setOptions([
        'attributes' => [
          'class' => ['cd-button'],
        ],
      ]);
      $carousel_items[] = [
        'title' => Markup::create($item_type->getLabel()),
        'description' => Markup::create($item_type->getDescription()),
        'image' => [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
        ],
        'button' => $link->toRenderable(),
      ];
    }

    return [
      '#theme' => 'link_carousel',
      '#items' => $carousel_items,
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
      'items' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'items');
    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured items'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Carousel item'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Title'),
          'url_string' => $this->t('Url'),
          'thumbnail' => $this->t('Thumbnail'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * Validate handler for portlet configuration form.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if ($this->isPreviewSubmit($form_state)) {
      return;
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
    // Make files permanent and add file usage.
    $usage_type = $this->getFileUsageType($entity);
    foreach ($files as $file) {
      if ($file->isPermanent()) {
        continue;
      }
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'ghi_blocks', $usage_type, $uuid);
    }
    $stored_files = $this->getStoredFiles($entity, $uuid);
    $removed_files = array_diff_key($stored_files, $files);
    if (!empty($removed_files)) {
      $this->cleanupFiles($removed_files, $entity, $uuid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(EntityInterface $entity, $uuid) {
    $files = $this->getFiles();
    $this->cleanupFiles($files, $entity, $uuid);
  }

  /**
   * Properly remove and cleanup files that are no longer in use.
   *
   * @param array $files
   *   The array of files to remove.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   * @param string $uuid
   *   The uuid of the block.
   */
  private function cleanupFiles(array $files, EntityInterface $entity, $uuid) {
    if (empty($files)) {
      return;
    }
    // Delete file usage and delete file.
    $usage_type = $this->getFileUsageType($entity);
    foreach ($files as $file) {
      $this->fileUsage->delete($file, 'ghi_blocks', $usage_type, $uuid);
      $file->delete();
    }
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
    foreach ($conf['items'] as $item) {
      $item_type = $item['item_type'] ?? NULL;
      if (!$item_type || $item_type !== 'carousel_item') {
        continue;
      }
      $images = $item['config']['value']['image'] ?? NULL;
      if (!$images) {
        continue;
      }
      foreach ($images as $fid) {
        $file = File::load($fid);
        if (!$file) {
          continue;
        }
        $files[$file->id()] = $file;
      }
    }
    return $files;
  }

  /**
   * Get the files stored for this block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   * @param string $uuid
   *   The uuid of the block.
   *
   * @return \Drupal\file\Entity\File[]
   *   An array of file objects.
   */
  private function getStoredFiles(EntityInterface $entity, $uuid) {
    $usage_type = $this->getFileUsageType($entity);
    $result = $this->connection->select('file_usage', 'f')
      ->fields('f', ['fid'])
      ->condition('module', 'ghi_blocks')
      ->condition('type', $usage_type)
      ->condition('id', $uuid)
      ->execute();
    $files = [];
    foreach ($result as $record) {
      $files[$record->fid] = File::load($record->fid);
    }
    return $files;
  }

  /**
   * Get the type string used in the `file_usage' table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated to this block.
   *
   * @return string
   *   Get a string that describes the file usage type.
   */
  private function getFileUsageType(EntityInterface $entity) {
    return implode(':', [
      $entity->getEntityTypeId(),
      $entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'carousel_item' => [],
    ];
    return $item_types;
  }

}
