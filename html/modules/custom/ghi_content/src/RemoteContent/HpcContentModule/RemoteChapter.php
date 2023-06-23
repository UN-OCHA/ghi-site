<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Render\Markup;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteChapter object.
 */
class RemoteChapter implements RemoteChapterInterface {

  /**
   * Raw chapter data from the remote source.
   *
   * @var mixed
   */
  private $data;

  /**
   * Source system.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $source;

  /**
   * Construct a new RemoteChapter object.
   */
  public function __construct($data, RemoteSourceInterface $source) {
    $this->data = $data;
    $this->source = $source;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
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
  public function getUuid() {
    return $this->data->uuid;
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
    return trim($this->data->title_short);
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
  public function getArticleIds() {
    return array_map(function ($article) {
      return $article->id;
    }, $this->data->articles);
  }

  /**
   * {@inheritdoc}
   */
  public function getArticles() {
    return array_map(function ($article) {
      return new RemoteArticle($article, $this->getSource());
    }, $this->data->articles);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->data->configuration ? Yaml::decode($this->data->configuration) : [];
  }

}
