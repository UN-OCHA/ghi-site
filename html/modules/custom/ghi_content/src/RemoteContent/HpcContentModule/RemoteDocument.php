<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteDocument object.
 */
class RemoteDocument extends RemoteDocumentBase {

  /**
   * Array of chapters, keyed by their id.
   *
   * @var object[]
   */
  private $chapters;

  /**
   * Construct a new RemoteDocument object.
   */
  public function __construct($data, RemoteSourceInterface $source) {
    parent::__construct($data, $source);
    $this->chapters = [];
    if (!empty($this->data->chapters)) {
      foreach ($this->data->chapters as $chapter) {
        $this->chapters[$chapter->id] = new RemoteChapter($chapter, $source);
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
  public function getShortTitle() {
    return trim($this->data->title_short ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getChapters($include_hidden = TRUE) {
    if ($include_hidden) {
      return $this->chapters;
    }
    return array_filter($this->chapters, function (RemoteChapterInterface $chapter) {
      return !$chapter->isHidden();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getChapter($id) {
    return $this->chapters[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreated() {
    return strtotime($this->data->created);
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
      $caption->location,
      $caption->text,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getImageCaptionMarkup($add_credits = FALSE) {
    $caption = $this->getImageCaption();
    if (!$caption) {
      return NULL;
    }
    $caption_text = $caption->text;
    if ($add_credits && $credits = $this->getImageCredits()) {
      $caption_text = new FormattableMarkup('@text <span class="credits">@credits</span>', [
        '@text' => $caption->text,
        '@credits' => $credits,
      ]);
    }
    return new FormattableMarkup('<h6 class="location">@location</h6><p class="text">@text</p>', [
      '@location' => $caption->location,
      '@text' => $caption_text,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentSpaceTags() {
    return $this->data->content_space ? $this->data->content_space->tags : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getContentTags() {
    return $this->data->tags ?? [];
  }

}
