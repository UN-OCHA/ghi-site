<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\Block\ImageProviderBlockInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem;
use Drupal\ghi_blocks\Traits\ManagedFileBlockTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Provides a 'LinkCarousel' block.
 *
 * @Block(
 *  id = "generic_link_carousel",
 *  admin_label = @Translation("Link Carousel"),
 *  category = @Translation("Generic elements"),
 *  title = FALSE
 * )
 */
class LinkCarousel extends GHIBlockBase implements ConfigurableTableBlockInterface, ImageProviderBlockInterface {

  use ConfigurationContainerTrait;
  use ManagedFileBlockTrait;
  use CustomLinkTrait;

  /**
   * {@inheritdoc}
   */
  public function provideImageUri() {
    $build = $this->buildContent();
    if (empty($build)) {
      return NULL;
    }
    $item = reset($build['#items']);
    return $item['thumbnail']['#uri'];
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

    // Get the responsive image style.
    $responsive_image_style = $this->entityTypeManager->getStorage('responsive_image_style')->load('link_carousel');

    $context = $this->getBlockContext();
    $carousel_items = [];
    ArrayHelper::sortArrayByNumericKey($conf['items'], 'weight', EndpointQuery::SORT_ASC);
    foreach ($conf['items'] as $item) {

      /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem $item_type */
      $item_type = $this->getItemTypePluginForColumn($item, $context);
      if (!$item_type instanceof CarouselItem) {
        continue;
      }

      $file = $item_type->getImage();
      if (!$file) {
        continue;
      }

      $link = $item_type->getLink();
      if (!$link) {
        continue;
      }
      $attributes = $link->getUrl()->getOption('attributes');
      $attributes['class'] = ['cd-button', 'read-more'];
      $link->getUrl()->setOption('attributes', $attributes);
      $carousel_items[] = [
        'tag_line' => Markup::create($item_type->getTagLine()),
        'title' => Markup::create($item_type->getLabel()),
        'description' => Markup::create($item_type->getDescription()),
        'thumbnail' => [
          '#theme' => 'image_style',
          '#uri' => $file->getFileUri(),
          '#style_name' => 'link_carousel_thumbnail',
        ],
        'image' => [
          '#theme' => 'ghi_image',
          '#responsive_image_style' => $responsive_image_style,
          '#url' => $file->getFileUri(),
          '#caption' => $item_type->getImageCaption() ?? NULL,
          '#credit' => $item_type->getImageCredit() ?? NULL,
        ],
        'button' => $link->toRenderable(),
      ];
    }

    if (empty($carousel_items)) {
      return;
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
          'tag_line' => $this->t('Tag line'),
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
    $submitted_files = $this->getFiles();
    $stored_files = $this->getStoredFiles($entity, $uuid);
    if (empty($submitted_files) && empty($stored_files)) {
      return;
    }
    $this->persistFiles($submitted_files, $entity, $uuid);
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
    foreach ($conf['items'] ?? [] as $item) {
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
