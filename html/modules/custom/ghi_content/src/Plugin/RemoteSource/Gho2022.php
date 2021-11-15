<?php

namespace Drupal\ghi_content\Plugin\RemoteSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides an attachment data item for configuration containers.
 *
 * @RemoteSource(
 *   id = "gho_2022",
 *   label = @Translation("GHO 2022"),
 *   description = @Translation("Import data directly from the GHO 2022 website."),
 * )
 */
class Gho2022 extends RemoteSourceBase implements RemoteSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getArticle($id) {
    $fields = [
      'id',
      'title',
    ];
    $fields['content'] = [
      'id',
      'type',
      'typeLabel',
      'rendered',
    ];
    return $this->fetchArticleData($id, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getArticleTitle($id) {
    $fields = ['title'];
    $article = $this->fetchArticleData($id, $fields);
    return $article->title;
  }

  /**
   * Fetch data for an article identified by $id.
   */
  private function fetchArticleData($id, array $fields) {
    $query = '{
      article(id:' . $id . ') {' . $this->getFieldString($fields) . '}
    }';

    $result = $this->query($query);
    if (!property_exists($result, 'article') || empty($result->article)) {
      return NULL;
    }
    return $result->article;
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraph($id) {
    $fields = [
      'id',
      'type',
      'typeLabel',
      'rendered',
    ];
    return $this->fetchParagraphData($id, $fields);
  }

  /**
   * Fetch data for an article identified by $id.
   */
  private function fetchParagraphData($id, array $fields) {
    $query = '{
      paragraph(id:' . $id . ') {' . $this->getFieldString($fields) . '}
    }';

    $result = $this->query($query);
    if (!property_exists($result, 'paragraph') || empty($result->paragraph)) {
      return NULL;
    }
    return $result->paragraph;
  }

  /**
   * {@inheritdoc}
   */
  public function searchArticlesByTitle($title) {
    $query = '{
      articleSearch(title:"' . $title . '") {
        count
        items {
          id
          title
        }
      }
    }';
    $result = $this->query($query);
    if (!property_exists($result, 'articleSearch') || empty($result->articleSearch)) {
      return NULL;
    }
    return $result->articleSearch;
  }

  /**
   * {@inheritdoc}
   */
  public function query($payload) {
    $body = '{"query": "query ' . str_replace("\n", " ", addslashes(trim($payload))) . '"}';
    $headers = [
      'Content-type: application/json',
    ];
    if ($hid_token = $this->getHidAccessToken()) {
      $headers[] = 'Authorization: Bearer ' . $hid_token;
    }

    $result = $this->httpClient->post($this->getRemoteEndpointUrl(), [
      'body' => $body,
      'headers' => $headers,
    ]);
    if ($result->getStatusCode() !== 200) {
      return NULL;
    }
    try {
      $response = json_decode((string) $result->getBody());
    }
    catch (\Exception $e) {
      // Just catch it for the moment.
    }
    if (!$response) {
      return NULL;
    }
    return property_exists($response, 'data') ? $response->data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function changeRessourceLinks($string) {
    $base_url = $this->getRemoteBaseUrl();
    $string = str_replace('"/themes/custom', '"' . $base_url . '/themes/custom', $string);
    $string = str_replace('"/sites/default/files', '"' . $base_url . '/sites/default/files', $string);
    $string = str_replace(' /sites/default/files', $base_url . '/sites/default/files', $string);
    return $string;
  }

  /**
   * Transform a fields array into a string for use in a GraphQL query.
   *
   * @param array $fields
   *   The input array.
   *
   * @return string
   *   The resulting string.
   */
  private function getFieldString(array $fields) {
    $string = [];
    foreach ($fields as $key => $field) {
      if (!is_array($field)) {
        $string[] = $field;
      }
      else {
        $string[] = $key . ' {' . $this->getFieldString($field) . '}';
      }
    }
    return implode(' ', $string);
  }

  /**
   * Get HID access token.
   */
  private function getHidAccessToken() {
    $session = $this->request->getCurrentRequest()->getSession();
    return !empty($session->get('social_auth_hid_access_token')) ? $session->get('social_auth_hid_access_token')->getToken() : NULL;
  }

  /**
   * Get the base url of the remote source.
   */
  private function getRemoteBaseUrl() {
    $config = $this->getConfiguration();
    return rtrim($config['base_url'], '/');
  }

  /**
   * Get the endpoint of the remote source.
   */
  private function getRemoteEndpoint() {
    $config = $this->getConfiguration();
    return trim($config['endpoint'], '/');
  }

  /**
   * Get the full url to the endpoint of the remote source.
   */
  private function getRemoteEndpointUrl() {
    return $this->getRemoteBaseUrl() . '/' . $this->getRemoteEndpoint();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'base_url' => 'https://gho.unocha.org',
      'endpoint' => 'ncms',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('Enter the base url for this remote source'),
      '#size' => 30,
      '#default_value' => $this->getRemoteBaseUrl(),
      '#required' => TRUE,
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('Enter the endpoint for this remote source'),
      '#size' => 30,
      '#default_value' => $this->getRemoteEndpoint(),
      '#required' => TRUE,
    ];

    return $form;
  }

}
