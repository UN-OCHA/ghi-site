<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteDocument object.
 */
class RemoteDocument extends RemoteDocumentBase {

  use RemoteContentImageTrait;

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
  public function getChapterNumber($id) {
    $position = array_search($id, array_keys($this->chapters));
    return $position !== FALSE ? $position + 1 : $position;
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
