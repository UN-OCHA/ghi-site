<?php

namespace Drupal\ghi_content\Traits;

use Drupal\Core\Url;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\node\NodeInterface;

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
    $node = $this->getCurrentNode();
    if ($node instanceof Article) {
      return $node;
    }
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
    $node = $this->getCurrentNode();
    if ($node instanceof Document) {
      return $node;
    }
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
    $node = $this->getCurrentNode();
    if ($node instanceof SectionNodeInterface) {
      return $node;
    }
    if ($node instanceof SubpageNodeInterface) {
      return $node->getParentNode();
    }
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
    $article_path_pos = strpos($path, '/article/');
    if ($article_path_pos === FALSE) {
      return NULL;
    }
    $article_url = substr($path, $article_path_pos);
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
    $document_path_pos = strpos($path, '/document/');
    if ($document_path_pos === FALSE) {
      return NULL;
    }
    $document_url = substr($path, $document_path_pos);
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
    $document_path_pos = strpos($path, '/document/');
    $article_path_pos = strpos($path, '/article/');
    $section_url = NULL;
    if ($document_path_pos !== FALSE) {
      $section_url = substr($path, 0, $document_path_pos);
    }
    elseif ($article_path_pos !== FALSE) {
      $section_url = substr($path, 0, $article_path_pos);
    }
    elseif (count(explode('/', ltrim($path, '/'))) > 2) {
      // Also support custom subpages and cluster pages, assuming that section
      // urls are always using this pattern /OBJECT_TYPE/ID, e.g. /plan/1234.
      $url_parts = explode('/', ltrim($path, '/'));
      $section_url = '/' . implode('/', array_slice($url_parts, 0, 2));
    }
    $section = $section_url ? $this->getNodeByUrlAlias($section_url) : NULL;
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
        // If this didn't work, see if there is a redirect by the given path
        // and try to load the node via that redirect.
        $redirects = $this->redirectRepository()->findBySourcePath(trim($path, '/'));
        $redirect = count($redirects) == 1 ? reset($redirects) : NULL;
        if ($redirect) {
          $redirect_alias = $redirect->getRedirectUrl()->toString();
          $loaded[$alias] = $this->getNodeByUrlAlias($redirect_alias);
        }
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
   * Get the current node object from the request if available.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node object or NULL if not found.
   */
  protected function getCurrentNode() {
    $node = $this->getRequest()->attributes->get('node');
    if ($node instanceof NodeInterface) {
      return $node;
    }
    $section_storage = $this->getRequest()->attributes->get('section_storage');
    if ($section_storage instanceof OverridesSectionStorage) {
      [$entity_type, $entity_id] = explode('.', $section_storage->getStorageId());
      $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    }
    if ($node instanceof NodeInterface) {
      return $node;
    }
    return NULL;
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
   * Get the redirect repository.
   *
   * @return \Drupal\redirect\RedirectRepository
   *   The redirect repository.
   */
  protected static function redirectRepository() {
    return \Drupal::service('redirect.repository');
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
