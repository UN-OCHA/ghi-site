<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides image metadata handling for HPC Content Module content.
 *
 * Classes using this trait inherit the protected raw data property from the
 * remote content base classes.
 */
trait RemoteContentImageTrait {

  /**
   * {@inheritdoc}
   */
  public function getImageUri() {
    $image_url = $this->data->image->imageUrl ?? NULL;
    return $image_url ? urldecode($image_url) : $image_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCredits() {
    return $this->data->image->credits ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCaption() {
    return $this->data->imageCaption ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCaptionPlain() {
    $caption = $this->getImageCaption();
    if (!$caption) {
      return NULL;
    }
    return implode(', ', array_filter([
      $caption->location ?? NULL,
      $caption->text ?? NULL,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCaptionMarkup($add_credits = FALSE) {
    $caption = $this->getImageCaption();
    // The remote API can return a caption object with NULL properties.
    $location = (string) ($caption->location ?? '');
    $text = (string) ($caption->text ?? '');
    $credits = $add_credits ? (string) ($this->getImageCredits() ?? '') : '';
    if ($location === '' && $text === '' && $credits === '') {
      return NULL;
    }

    $markup = '';
    $arguments = [];
    if ($location !== '') {
      $markup .= '<h6 class="location">@location</h6>';
      $arguments['@location'] = $location;
    }
    if ($text !== '' || $credits !== '') {
      $markup .= '<p class="text">';
      if ($text !== '') {
        $markup .= '@text';
        $arguments['@text'] = $text;
      }
      if ($credits !== '') {
        $markup .= ($text !== '' ? ' ' : '') . '<span class="credits">@credits</span>';
        $arguments['@credits'] = $credits;
      }
      $markup .= '</p>';
    }
    return new FormattableMarkup($markup, $arguments);
  }

}
