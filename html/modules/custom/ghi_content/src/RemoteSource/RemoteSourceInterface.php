<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;

/**
 * Interface class for remote sources.
 */
interface RemoteSourceInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface, ConfigurableInterface {

  /**
   * Get the label for the plugin.
   */
  public function getPluginLabel();

  /**
   * Get the plugin description if available.
   */
  public function getPluginDescription();

  /**
   * Get content by type id.
   *
   * @param string $type
   *   The type of the content on the remote.
   * @param int $id
   *   The id of the content on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteContentInterface
   *   The content object.
   */
  public function getContent($type, $id);

  /**
   * Get a document by id.
   *
   * @param int $id
   *   The id of the document on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface
   *   The document object.
   */
  public function getDocument($id);

  /**
   * Search documents by title.
   *
   * @param string $title
   *   The title to search.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface[]
   *   The set of matching document objects.
   */
  public function searchDocumentsByTitle($title);

  /**
   * Get an article by id.
   *
   * @param int $id
   *   The id of the article on the remote.
   * @param bool $rendered
   *   Allow to switch off rendering.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   The article object.
   */
  public function getArticle($id, $rendered = TRUE);

  /**
   * Search articles by title.
   *
   * @param string $title
   *   The title to search.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface[]
   *   The set of matching article objects.
   */
  public function searchArticlesByTitle($title);

  /**
   * Get a paragraph by id.
   *
   * @param int $id
   *   The id of the paragraph on the remote.
   * @param bool $rendered
   *   Allow to switch off rendering.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface
   *   The result object.
   */
  public function getParagraph($id, $rendered = TRUE);

  /**
   * Get a tag by name.
   *
   * @param int $name
   *   The name of the tag on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteTagInterface
   *   The result object.
   */
  public function getTag($name);

  /**
   * Issue a query against a remote source.
   *
   * @param string $payload
   *   The payload for the query.
   * @param array $cache_tags
   *   An array of cache tags for the query result.
   *
   * @return \Drupal\ghi_content\RemoteResponse\RemoteResponseInterface
   *   A response object.
   */
  public function query($payload, array $cache_tags = []);

  /**
   * Change the links to public ressources.
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The final string.
   *
   * @todo Bad idea. Find a different solution!
   */
  public function changeRessourceLinks($string);

  /**
   * Build a configuration form for this remote source.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Check the connection.
   *
   * @return bool
   *   TRUE if a connection can be established, FALSE otherwhise.
   */
  public function checkConnection();

  /**
   * Save the plugin configuration.
   */
  public function saveConfiguration();

  /**
   * Get the base url of the remote source.
   *
   * @return string
   *   The remote url as a string.
   */
  public function getRemoteBaseUrl();

  /**
   * Get the full url to the endpoint of the remote source.
   *
   * @return string
   *   The remote endpoint url as a string.
   */
  public function getRemoteEndpointUrl();

  /**
   * Get the url to a content item.
   *
   * @param int $id
   *   The id of the content on the remote.
   * @param string $type
   *   The type of link. Defaults to "canonical".
   *
   * @return \Drupal\core\Url
   *   A url object.
   */
  public function getContentUrl($id, $type = 'canonical');

  /**
   * Get the file size for the given url.
   *
   * This should be done without transferring the file.
   *
   * @param string $uri
   *   The URI as a string.
   *
   * @return int
   *   The size of the file content.
   */
  public function getFileSize($uri);

  /**
   * Get the file content for the given url.
   *
   * @param string $uri
   *   The URI as a string.
   *
   * @return string
   *   A file contents.
   */
  public function getFileContent($uri);

  /**
   * Get a map with links to replace.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $paragraph
   *   The remote paragraph object for which to create the link map.
   *
   * @return array
   *   An array of link url strings, key is the original link in the rendered
   *   source, value is a valid url string in the local system.
   */
  public function getLinkMap(RemoteParagraphInterface $paragraph);

  /**
   * Get the ids to import for the given type.
   *
   * @param string $type
   *   The type of content.
   * @param string[] $tags
   *   Optional argument to filter the source data by tag names.
   *
   * @return int[]
   *   An array of ids from the remote source.
   */
  public function getImportIds($type, ?array $tags = NULL);

  /**
   * Get the import meta data for the given type.
   *
   * @param string $type
   *   The type of content.
   * @param string[] $tags
   *   Optional argument to filter the source data by tag names.
   *
   * @return array[]
   *   An array of arrays for the meta data of each article.
   */
  public function getImportMetaData($type, ?array $tags);

  /**
   * Get the import data for a single content item.
   *
   * @param string $type
   *   The type of content.
   * @param int $id
   *   The content id.
   *
   * @return array
   *   Raw import data from the remote source.
   */
  public function getImportData($type, $id);

  /**
   * Get the ids to import for the given type.
   *
   * @param string $type
   *   The type of content.
   * @param string[] $tags
   *   Optional argument to filter the source data by tag names.
   *
   * @return \Iterator
   *   An iterator object.
   */
  public function getIterator($type, $tags = NULL);

  /**
   * Disable the cache.
   *
   * @param bool $status
   *   TRUE to disable the cache, FALSE to use the cache.
   */
  public function disableCache($status = TRUE);

  /**
   * Set the cache base time for queries.
   *
   * @param int $timestamp
   *   The cache base time to use for queries.
   */
  public function setCacheBaseTime($timestamp);

  /**
   * Get the cache base time for queries.
   *
   * @return int
   *   The cache base time to use for queries.
   */
  public function getCacheBaseTime();

}
