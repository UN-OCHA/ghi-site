<?php

namespace Drupal\ghi_element_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerInterface;
use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerManager;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync element service class.
 */
class SyncManager implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The paragraph handler manager.
   *
   * @var \Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerManager
   */
  protected $paragraphHandlerManager;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The UUID of the component.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuid;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Public constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ParagraphHandlerManager $paragraph_handler_manager, Client $http_client, UuidInterface $uuid, TimeInterface $time, AccountProxyInterface $user) {
    $this->config = $config_factory;
    $this->paragraphHandlerManager = $paragraph_handler_manager;
    $this->httpClient = $http_client;
    $this->uuidGenerator = $uuid;
    $this->time = $time;
    $this->currentUser = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.ghi_paragraph_handler'),
      $container->get('http_client'),
      $container->get('uuid'),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * Sync a node form a remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be synced.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   *
   * @return bool
   *   Indicating whether the node has been sucessfully processed or not.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function syncNode(NodeInterface $node, MessengerInterface $messenger = NULL) {
    if ($messenger === NULL) {
      $messenger = $this->messenger();
    }
    $settings = $this->config->get('ghi_element_sync.settings');

    $original_id = $node->field_original_id->value;
    $bundle = $node->bundle();

    if (empty($settings->get('sync_source'))) {
      throw new SyncException('Error: Source is not configured');
    }
    $source_url = rtrim($settings->get('sync_source'), '/');
    $url = $source_url . '/admin/hpc/plan-elements/export/' . $original_id . '/' . $bundle . '?time=' . microtime(TRUE);
    $cookies = [
      'ghi_access' => $settings->get('access_key'),
    ];
    $jar = CookieJar::fromArray($cookies, parse_url($settings->get('sync_source'), PHP_URL_HOST));
    $response = $this->httpClient->request('GET', $url, ['cookies' => $jar]);

    $code = $response->getStatusCode();
    if ($code != 200) {
      throw new SyncException('Error: Invalid response');
    }

    $body = $response->getBody()->getContents();
    if (empty($body)) {
      throw new SyncException('Error: Empty response');
    }

    $data = json_decode($body);
    if (empty($data->status)) {
      throw new SyncException('Error: Access key misconfigured or object not valid');
    }
    if (empty($data->elements)) {
      // No error, but nothing to do either.
      return FALSE;
    }

    foreach ($data->elements as $element) {

      $definition = $this->getTargetPluginDefinition($element->type);
      if (!$definition) {
        continue;
      }
      $class = $definition['class'];

      $paragraph = NULL;
      $existing_paragraph_handler = $this->getExistingSyncedParagraphHandler($node, $element);
      if ($existing_paragraph_handler) {
        // Update an existing component.
        $paragraph = $existing_paragraph_handler->getParagraph();
        $configuration = $class::mapConfig($element->configuration) + $existing_paragraph_handler->getConfig();
        $existing_paragraph_handler->setConfig($configuration);
        $paragraph->save();
        $messenger->addMessage($this->t('Updated %plugin_title', [
          '%plugin_title' => $definition['label'],
        ]));
      }
      else {
        // Append a new component.
        $messenger->addMessage($this->t('Added %plugin_title', [
          '%plugin_title' => $definition['label'],
        ]));
        $config = [
          'sync' => [
            'source_uuid' => $element->uuid,
          ],
        ];
        $config += $class::mapConfig($element->configuration);

        // Create new paragraph entity.
        $paragraph = Paragraph::create([
          'type' => $definition['id'],
        ]);
        $paragraph->save();
        $plugin = ghi_paragraph_handler_get_handler($paragraph);
        $plugin->setConfig($config);
        $node->field_content->appendItem($paragraph);
      }

    }

    $node->setNewRevision(TRUE);
    $node->revision_log = $this->t('Synced page elements from @source_url', ['@source_url' => $source_url]);
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionUserId($this->currentUser->id());
    $node->save();

    return TRUE;
  }

  /**
   * Find a paragraph handler corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param object $element
   *   The configuration object from the sync source.
   *
   * @return \Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerInterface|null
   *   Either a matching paragraph handler, or NULL.
   */
  private function getExistingSyncedParagraphHandler(NodeInterface $node, $element) {
    $paragraphs = $this->getParagraphs($node);
    foreach ($paragraphs as $paragraph) {
      if (!$plugin = ghi_paragraph_handler_get_handler($paragraph)) {
        continue;
      }
      if (!$plugin instanceof ParagraphHandlerInterface) {
        continue;
      }
      $configuration = $plugin->getConfig();
      if (!empty($configuration['sync']) && !empty($configuration['sync']['source_uuid']) && $configuration['sync']['source_uuid'] == $element->uuid) {
        return $plugin;
      }
    }
    return NULL;
  }

  /**
   * Get sections for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   An array of layout builder sections.
   */
  private function getParagraphs(NodeInterface $node) {
    return $node->field_content->referencedEntities();
  }

  /**
   * Get the plugin definition for the target paragraph handler.
   *
   * @param string $element_type
   *   The element type of the source site.
   *
   * @return array|null
   *   If a defintion is found, returns the definitions array, NULL otherwhise.
   */
  private function getTargetPluginDefinition($element_type) {
    foreach ($this->paragraphHandlerManager->getDefinitions() as $definition) {
      $class = $definition['class'];
      if (!in_array('Drupal\ghi_element_sync\SyncableParagraphInterface', class_implements($class))) {
        continue;
      }
      if ($class::getSourceElementKey() != $element_type) {
        continue;
      }
      return $definition;
    }
  }

}
