<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Traits\OptionalLinkTrait;

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

  use OptionalLinkTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['value'] = [
      '#type' => 'carousel_item',
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];

    $element['#element_validate'] = [
      [static::class, 'validateElement'],
    ];
    return $element;
  }

  /**
   * Validate the link form element.
   *
   * @param array $element
   *   The element build in ::buildForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The complete form that contains the element.
   */
  public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // @todo This repeats logic from
    // \Drupal\ghi_form_elements\Element\OptionalLink::elementValidate().
    $url = $form_state->getValue($element['#parents'])['value']['url'];
    $transformed_url = self::transformUrl($url);
    if (!$transformed_url) {
      $form_state->setError($element['value']['url'], t('The link URL must be valid and accessible.'));
    }
    if (!$form_state->hasAnyErrors() && $transformed_url !== $url) {
      $form_state->setValue(array_merge($element['#parents'], ['value', 'url']), $transformed_url);
    }

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
   * Get the link.
   *
   * @return \Drupal\Core\Link
   *   A link object.
   */
  public function getLink() {
    $label = $this->getButtonLabel() ?? $this->t('Read more');
    return $this->getLinkFromUri($this->config['value']['url'], $label);
  }

  /**
   * Get the url.
   *
   * @return \Drupal\Core\Url
   *   A url object.
   */
  public function getUrl() {
    try {
      $link = $this->getLink();
      return $link->getUrl();
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
   * Get the button label.
   *
   * @return string|null
   *   The button label.
   */
  public function getButtonLabel() {
    return (string) ($this->config['value']['button_label'] ?? NULL) ?: NULL;
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
