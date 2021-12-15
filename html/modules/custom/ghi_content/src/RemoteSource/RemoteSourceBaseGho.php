<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\RemoteResponse\RemoteResponse;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * GHO specific remote source base class.
 */
abstract class RemoteSourceBaseGho extends RemoteSourceBase {

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

    $response = $this->query($query);
    if (!$response->has('article')) {
      return NULL;
    }
    return $response->get('article');
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

    $response = $this->query($query);
    if (!$response->has('paragraph')) {
      return NULL;
    }
    return $response->get('paragraph');
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
    $response = $this->query($query);
    if (!$response->has('articleSearch')) {
      return NULL;
    }
    return $response->get('articleSearch');
  }

  /**
   * {@inheritdoc}
   */
  public function query($payload) {
    $body = '{"query": "query ' . str_replace("\n", " ", addslashes(trim($payload))) . '"}';
    $headers = [
      'Content-type: application/json',
    ];
    if ($hid_user_id = $this->hidUserData->getId()) {
      $headers['hid-user'] = $hid_user_id;
    }

    $cookies = ['gho_access' => $this->getRemoteAccessKey()];
    $jar = CookieJar::fromArray($cookies, parse_url($this->getRemoteBaseUrl(), PHP_URL_HOST));

    $response = new RemoteResponse();
    try {
      $result = $this->httpClient->post($this->getRemoteEndpointUrl(), [
        'body' => $body,
        'headers' => $headers,
        'cookies' => $jar,
      ]);
    }
    catch (ClientException $e) {
      $response->setCode($e->getCode());
      return $response;
    }
    catch (ServerException $e) {
      $response->setCode($e->getCode());
      return $response;
    }

    if (!$result || $result->getStatusCode() !== 200) {
      $response->setCode($result ? $result->getStatusCode() : 500);
      return $response;
    }
    try {
      $body_data = json_decode((string) $result->getBody());
      $response->setData(is_object($body_data) && property_exists($body_data, 'data') ? $body_data->data : NULL);
    }
    catch (\Exception $e) {
      // Just catch it for the moment.
    }
    return $response;
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
   * Get the base url of the remote source.
   */
  private function getRemoteAccessKey() {
    $config = $this->getConfiguration();
    return $config['access_key'];
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
