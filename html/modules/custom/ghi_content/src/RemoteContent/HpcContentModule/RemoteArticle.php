<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteArticleBase;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteArticle object.
 */
class RemoteArticle extends RemoteArticleBase {

  use RemoteContentImageTrait;

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
    if (!empty($data->content)) {
      foreach ($data->content as $paragraph) {
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
  public function getShortTitle() {
    return trim($this->data->title_short ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getSection() {
    return trim($this->data->section);
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
  public function getParagraph($id) {
    return $this->paragraphs[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraphs() {
    return $this->paragraphs ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasSubarticle(RemoteArticleInterface $remote_article) {
    foreach ($this->getParagraphs() as $paragraph) {
      if ($paragraph->getType() != 'sub_article') {
        continue;
      }
      $article_id = $paragraph->getConfiguration()['article_id'] ?? NULL;
      if ($article_id && $article_id == $remote_article->getId()) {
        return TRUE;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentIds() {
    $document_ids = array_map(function ($item) {
      return $item->id;
    }, array_filter($this->data->documents ?? []));
    return $document_ids;
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
