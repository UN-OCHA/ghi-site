<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;

/**
 * Provides a carousel item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "carousel_item",
 *   label = @Translation("Carousel item"),
 *   description = @Translation("This item displays an image with text to be used as part of a carousel."),
 * )
 */
class CarouselItem extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['value'] = [
      '#type' => 'carousel_item',
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];
    return $element;
  }

  /**
   * Get the tag line.
   */
  public function getTagLine() {
    return $this->config['value']['tag_line'] ?? NULL;
  }

  /**
   * Get the description.
   */
  public function getDescription() {
    return $this->config['value']['description'];
  }

  /**
   * Get the image.
   *
   * @return \Drupal\file\Entity\File
   *   A file object for the image file.
   */
  public function getImage() {
    return File::load(reset($this->config['value']['image']));
  }

  /**
   * Get the image credit.
   *
   * @return string
   *   A credit for the image.
   */
  public function getImageCredit() {
    return $this->config['value']['image_credit'] ?? NULL;
  }

  /**
   * Get the image caption.
   *
   * @return string
   *   A caption for the image.
   */
  public function getImageCaption() {
    $credit = $this->getImageCredit();
    $caption = $this->config['value']['image_caption'] ?? NULL;
    if ($caption && $credit) {
      return new FormattableMarkup('@text <span class="credits">@credits</span>', [
        '@text' => $caption,
        '@credits' => $credit,
      ]);
    }
    return $caption ?? NULL;
  }

  /**
   * Get the url.
   *
   * @return \Drupal\Core\Url
   *   A url object.
   */
  public function getUrl() {
    try {
      return Url::fromUri($this->config['value']['url']);
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
  }

  /**
   * Get the url as a string.
   *
   * @return string
   *   The url as a string.
   */
  public function getUrlString() {
    $url = $this->getUrl();
    return $url instanceof Url ? $url->toString() : NULL;
  }

  /**
   * Get the image as thumbnail.
   *
   * @return array
   *   A render array.
   */
  public function getThumbnail() {
    $image = $this->getImage();
    if (!$image) {
      return NULL;
    }
    return [
      '#theme' => 'image_style',
      '#style_name' => 'thumbnail',
      '#uri' => $image->getFileUri(),
    ];
  }

}
