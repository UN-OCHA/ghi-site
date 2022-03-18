<?php

namespace Drupal\ghi_content\RemoteContent\Gho;

use Drupal\ghi_content\RemoteContent\RemoteArticleBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteArticle object.
 */
class RemoteArticle extends RemoteArticleBase {

  /**
   * Raw article data from the remote source.
   *
   * @var mixed
   */
  private $data;

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
    $this->data = $data;
    $this->source = $source;
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
  public function getImageUri() {
    return $this->data->thumbnail->imageUrl ?? NULL;
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
