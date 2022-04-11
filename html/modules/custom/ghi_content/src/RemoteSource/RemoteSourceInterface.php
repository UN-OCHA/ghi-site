<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

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
   * Get an article by id.
   *
   * @param int $id
   *   The id of the article on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   The article object.
   */
  public function getArticle($id);

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
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface
   *   The result object.
   */
  public function getParagraph($id);

  /**
   * Issue a query against a remote source.
   *
   * @param string $payload
   *   The payload for the query.
   *
   * @return \Drupal\ghi_content\RemoteResponse\RemoteResponseInterface
   *   A response object.
   */
  public function query($payload);

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
   * Get the file data for the given url.
   *
   * @param string $uri
   *   The URI as a string.
   *
   * @return string
   *   A file contents.
   */
  public function getFileContent($uri);

  /**
   * Get the import source for a remote system.
   *
   * @param array $tags
   *   Optional argument to filter the source data by tag names.
   *
   * @return array
   *   An array of source identifiers.
   */
  public function importSource(array $tags = NULL);

}
