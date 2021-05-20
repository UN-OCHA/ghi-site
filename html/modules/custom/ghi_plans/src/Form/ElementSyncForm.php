<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerManager;
use Drupal\ghi_plans\Plugin\ParagraphHandler\SyncableParagraphInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Form with examples on how to use batch api.
 */
class ElementSyncForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ParagraphHandlerManager $paragraph_handler_manager, Client $http_client, UuidInterface $uuid, MessengerInterface $messenger, TimeInterface $time, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->paragraphHandlerManager = $paragraph_handler_manager;
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
      $container->get('plugin.manager.ghi_paragraph_handler'),
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

    $original_id = $node->field_original_id->value;
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
        $this->messenger->addMessage($this->t('Updated %plugin_title', [
          '%plugin_title' => $definition['label'],
        ]));
      }
      else {
        // Append a new component.
        $this->messenger->addMessage($this->t('Added %plugin_title', [
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
    $node->setRevisionUserId($this->currentUser()->id());
    $node->save();
  }

  /**
   * Find a paragraph handler corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param object $element
   *   The configuration object from the sync source.
   *
   * @return \Drupal\ghi_plans\Plugin\ParagraphHandler\PlanBaseClass|null
   *   Either a matching paragraph handler, or NULL.
   */
  private function getExistingSyncedParagraphHandler(NodeInterface $node, $element) {
    $paragraphs = $this->getParagraphs($node);
    foreach ($paragraphs as $paragraph) {
      if (!$plugin = ghi_paragraph_handler_get_handler($paragraph)) {
        continue;
      }
      if (!$plugin instanceof SyncableParagraphInterface) {
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
      if (!in_array('Drupal\ghi_plans\Plugin\ParagraphHandler\SyncableParagraphInterface', class_implements($class))) {
        continue;
      }
      if ($class::getSourceElementKey() != $element_type) {
        continue;
      }
      return $definition;
    }
  }

}
