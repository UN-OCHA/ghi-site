<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteArticleBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteArticle object.
 */
class RemoteArticle extends RemoteArticleBase {

  /**
   * Array of paragraphs, keyed by their id.
   *
   * @var object[]
   */
  private $paragraphs;

  /**
   * Construct a new RemoteArticle object.
   */
  public function __construct($data, RemoteSourceInterface $source) {
    parent::__construct($data, $source);
    $this->paragraphs = [];
    if (!empty($this->data->content)) {
      foreach ($this->data->content as $paragraph) {
        $this->paragraphs[$paragraph->id] = new RemoteParagraph($paragraph, $source);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->data->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return trim($this->data->title);
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdated() {
    return strtotime($this->data->updated);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return Markup::create($this->data->summary);
  }

  /**
   * {@inheritdoc}
   */
  public function getImageUri() {
    return $this->data->image->imageUrl ?? NULL;
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
      $caption->location,
      $caption->text,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCaptionMarkup() {
    $caption = $this->getImageCaption();
    if (!$caption) {
      return NULL;
    }
    return new FormattableMarkup('<div class="image-caption"><div class="location">@location</div><div class="text">@text</div></div>', [
      '@location' => $caption->location,
      '@text' => $caption->text,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraph($id) {
    return $this->paragraphs[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraphs() {
    return $this->paragraphs;
  }

  /**
   * {@inheritdoc}
   */
  public function getMajorTags() {
    return $this->data->content_space ? $this->data->content_space->tags : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMinorTags() {
    return $this->data->tags ?? [];
  }

}
