<?php

namespace Drupal\ghi_content\Traits;

use Drupal\Core\Url;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Trait for handling content paths.
 */
trait ContentPathTrait {

  /**
   * Get the current article node if available.
   *
   * @return \Drupal\ghi_content\Entity\Article|null
   *   The article node or NULL if not found.
   */
  protected function getCurrentArticleNode() {
    $path = $this->getCurrentPath();
    return $this->getArticleNodeFromPath($path, TRUE);
  }

  /**
   * Get the current document node if available.
   *
   * @return \Drupal\ghi_content\Entity\Document|null
   *   The document node or NULL if not found.
   */
  protected function getCurrentDocumentNode() {
    $path = $this->getCurrentPath();
    return $this->getDocumentNodeFromPath($path, TRUE);
  }

  /**
   * Get the current section node if available.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node or NULL if not found.
   */
  protected function getCurrentSectionNode() {
    $path = $this->getCurrentPath();
    return $this->getSectionNodeFromPath($path);
  }

  /**
   * Get an article node from the current path.
   *
   * @return \Drupal\ghi_content\Entity\Article|null
   *   The article node or NULL if not found.
   */
  protected function getArticleNodeFromPath($path) {
    if (strpos($path, '/article/') === FALSE) {
      return NULL;
    }
    $article_url = substr($path, strpos($path, '/article/'));
    $article = $this->getNodeByUrlAlias($article_url);
    return $article instanceof Article ? $article : NULL;
  }

  /**
   * Get a document node from the current path.
   *
   * @return \Drupal\ghi_content\Entity\Document|null
   *   The document node or NULL if not found.
   */
  protected function getDocumentNodeFromPath($path, $root = FALSE) {
    if (strpos($path, '/document/') === FALSE) {
      return NULL;
    }
    $document_url = substr($path, strpos($path, '/document/'));
    if (strpos($document_url, '/article/') > 0) {
      $document_url = substr($path, 0, strpos($path, '/article/'));
    }
    $document = $this->getNodeByUrlAlias($document_url);
    return $document instanceof Document ? $document : NULL;
  }

  /**
   * Get a section node from the current path.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node or NULL if not found.
   */
  protected function getSectionNodeFromPath($path) {
    if (!strpos($path, '/document/')) {
      return NULL;
    }
    $section_url = substr($path, 0, strpos($path, '/document/'));
    $section = $this->getNodeByUrlAlias($section_url);
    return $section instanceof SectionNodeInterface ? $section : NULL;
  }

  /**
   * Get a node by it's alias.
   *
   * @param string $alias
   *   The alias to look for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node object or NULL if not found.
   */
  protected function getNodeByUrlAlias($alias) {
    $loaded = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($alias, $loaded)) {
      $path = $this->pathAliasManager()->getPathByAlias($alias);
      $loaded[$alias] = NULL;
      try {
        $params = Url::fromUri("internal:" . $path)->getRouteParameters();
        $entity_type = key($params);
        $loaded[$alias] = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
      }
      catch (\Exception $e) {
        // Just catch any issue and pretend this didn't happen.
      }
    }
    return $loaded[$alias];
  }

  /**
   * Get the current path.
   *
   * @return string
   *   The current path.
   */
  protected function getCurrentPath() {
    $request = $this->getRequest();
    return $request->getPathInfo();
  }

  /**
   * Get the path alias manager service.
   *
   * @return \Drupal\path_alias\AliasManager
   *   The path alias manager service.
   */
  protected static function pathAliasManager() {
    return \Drupal::service('path_alias.manager');
  }

  /**
   * Get the current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The current request object.
   */
  private static function getRequest() {
    return \Drupal::request();
  }

}
