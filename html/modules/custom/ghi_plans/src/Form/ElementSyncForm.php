<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * Form with examples on how to use batch api.
 */
class ElementSyncForm extends FormBase {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, Client $http_client, UuidInterface $uuid, MessengerInterface $messenger, TimeInterface $time, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->httpClient = $http_client;
    $this->uuidGenerator = $uuid;
    $this->messenger = $messenger;
    $this->time = $time;
    $this->currentUser = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('http_client'),
      $container->get('uuid'),
      $container->get('messenger'),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_plans_element_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#node'] = $node;

    $form['sync_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync all elements'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form['#node'];
    $settings = $this->config('ghi_plans.settings');

    $original_id = NodeHelper::getOriginalIdFromNode($node);
    $bundle = $node->bundle();

    if (empty($settings->get('sync_source'))) {
      $this->messenger->addError($this->t('Error: Source is not configured'));
      return;
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
      $this->messenger->addError($this->t('Error: Invalid response'));
      return;
    }

    $body = $response->getBody()->getContents();
    if (empty($body)) {
      $this->messenger->addError($this->t('Error: Empty response'));
      return;
    }

    $data = json_decode($body);
    if (empty($data->status)) {
      $this->messenger->addError($this->t('Error: Access key misconfigured or object not valid'));
      return;
    }
    if (empty($data->elements)) {
      $this->messenger->addError($this->t('Error: No elements on the remote source'));
      return;
    }

    $sections = $this->getNodeSections($node);
    $delta = 0;

    foreach ($data->elements as $element) {
      $definition = $this->blockManager->getDefinition($element->type, FALSE);
      if (!$definition) {
        continue;
      }

      $class = $definition['class'];
      if (!in_array('Drupal\ghi_blocks\Plugin\Block\SyncableBlockInterface', class_implements($class))) {
        continue;
      }

      $existing_component = $this->getExistingSyncedComponent($node, $element);
      if ($existing_component) {
        // Update an existing component.
        $configuration = $class::mapConfig($element->configuration) + $existing_component->get('configuration');
        $existing_component->setConfiguration($configuration);
        $this->messenger->addMessage($this->t('Updated %plugin_title', [
          '%plugin_title' => $definition['admin_label'],
        ]));
      }
      else {
        // Append a new component.
        $this->messenger->addMessage($this->t('Added %plugin_title', [
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
    $node->setNewRevision(TRUE);
    $node->revision_log = $this->t('Synced page elements from @source_url', ['@source_url' => $source_url]);
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionUserId($this->currentUser()->id());
    $node->save();
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
