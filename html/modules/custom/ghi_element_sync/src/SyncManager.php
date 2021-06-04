<?php

namespace Drupal\ghi_element_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
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
  use LayoutEntityHelperTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

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
  public function __construct(ConfigFactoryInterface $config_factory, BlockManagerInterface $block_manager, Client $http_client, UuidInterface $uuid, TimeInterface $time, AccountProxyInterface $user) {
    $this->config = $config_factory;
    $this->blockManager = $block_manager;
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
      $container->get('plugin.manager.block'),
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
   * @param bool $revisions
   *   Whether new revisions should be created.
   *
   * @return bool
   *   Indicating whether the node has been sucessfully processed or not.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function syncNode(NodeInterface $node, MessengerInterface $messenger = NULL, $revisions = FALSE) {
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

    $sections = $this->getNodeSections($node);
    $delta = 0;

    foreach ($data->elements as $element) {
      $definition = $this->blockManager->getDefinition($element->type, FALSE);
      if (!$definition) {
        continue;
      }
      $class = $definition['class'];
      if (!in_array('Drupal\ghi_element_sync\SyncableBlockInterface', class_implements($class))) {
        continue;
      }

      $existing_component = $this->getExistingSyncedComponent($node, $element);
      if ($existing_component) {
        // Update an existing component.
        $configuration = $class::mapConfig($element->configuration) + $existing_component->get('configuration');
        $existing_component->setConfiguration($configuration);
        $messenger->addMessage($this->t('Updated %plugin_title', [
          '%plugin_title' => $definition['admin_label'],
        ]));
      }
      else {
        // Append a new component.
        $messenger->addMessage($this->t('Added %plugin_title', [
          '%plugin_title' => $definition['admin_label'],
        ]));
        $config = [
          'id' => $definition['id'],
          'provider' => $definition['provider'],
          'data_sources' => $definition['data_sources'],
          'context_mapping' => [
            'node' => 'layout_builder.entity',
          ],
          'sync' => [
            'source_uuid' => $element->uuid,
          ],
        ];
        $config += $class::mapConfig($element->configuration);

        $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
        $sections[$delta]->appendComponent($component);
      }

    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    if ($revisions) {
      $node->setNewRevision(TRUE);
      $node->revision_log = $this->t('Synced page elements from @source_url', ['@source_url' => $source_url]);
      $node->setRevisionCreationTime($this->time->getRequestTime());
      $node->setRevisionUserId($this->currentUser->id());
    }
    $node->save();

    return TRUE;
  }

  /**
   * Find a section component corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param object $element
   *   The configuration object from the sync source.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   Either a matching component, or NULL.
   */
  private function getExistingSyncedComponent(NodeInterface $node, $element) {
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    foreach ($sections[0]->getComponents() as $component) {
      $configuration = $component->get('configuration');
      if (!empty($configuration['sync']) && !empty($configuration['sync']['source_uuid']) && $configuration['sync']['source_uuid'] == $element->uuid) {
        return $component;
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
  private function getNodeSections(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage) {
      return NULL;
    }
    $sections = $section_storage->getSections();
    return $sections;
  }

}
