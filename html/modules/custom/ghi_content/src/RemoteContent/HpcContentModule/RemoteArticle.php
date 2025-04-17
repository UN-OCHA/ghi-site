<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteArticleBase;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
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
