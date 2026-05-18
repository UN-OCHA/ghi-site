<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\ghi_content\RemoteSource\RemoteRefreshSourceInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives signed remote content refresh notifications.
 *
 * The contrib Webhooks module is a possible generic alternative:
 * https://www.drupal.org/project/webhooks. This receiver stays custom while
 * that module is alpha and does not provide the replay protection, secret
 * handling, and narrow CM-to-HA contract we need here.
 */
class RemoteRefreshController extends ControllerBase {

  /**
   * Queue id for remote content refresh jobs.
   */
  const QUEUE_ID = 'ghi_content_remote_refresh';

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The remote source plugin manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected $remoteSourceManager;

  /**
   * The expirable key/value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirableFactory;

  /**
   * Remote refresh source instances keyed by source id.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteRefreshSourceInterface[]
   */
  protected $remoteSources = [];

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a remote refresh controller.
   */
  public function __construct(QueueFactory $queue_factory, RemoteSourceManager $remote_source_manager, KeyValueExpirableFactoryInterface $key_value_expirable_factory, LoggerInterface $logger) {
    $this->queueFactory = $queue_factory;
    $this->remoteSourceManager = $remote_source_manager;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.remote_source'),
      $container->get('keyvalue.expirable'),
      $container->get('logger.channel.ghi_content')
    );
  }

  /**
   * Receive a refresh notification.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function receive(Request $request) {
    $body = $request->getContent();
    if (strlen($body) > $this->getConfiguredMaxBodySize()) {
      return new JsonResponse(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    try {
      $payload = Json::decode($body);
    }
    catch (\Exception $e) {
      return new JsonResponse(['message' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
    }

    $errors = $this->validatePayload($payload);
    if (!empty($errors)) {
      return new JsonResponse(['message' => implode(' ', $errors)], Response::HTTP_BAD_REQUEST);
    }

    if (strlen($body) > $this->getMaxBodySize($payload['source'])) {
      return new JsonResponse(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    if (!$this->validateSignature($request, $body, $payload['source'])) {
      return new JsonResponse(['message' => 'Invalid signature.'], Response::HTTP_FORBIDDEN);
    }

    if (!$this->claimDeliveryId($payload)) {
      $this->logger->notice('Rejected duplicate remote refresh delivery @delivery_id from @source.', [
        '@delivery_id' => $payload['deliveryId'],
        '@source' => $payload['source'],
      ]);
      return new JsonResponse(['queued' => FALSE], Response::HTTP_ACCEPTED);
    }

    if (($payload['event'] ?? NULL) === 'ping') {
      $this->logger->info('Validated @event event from @source.', [
        '@event' => $payload['event'],
        '@source' => $payload['source'],
      ]);
      return new JsonResponse(['checked' => TRUE], Response::HTTP_ACCEPTED);
    }

    $item = (object) [
      'source' => $payload['source'],
      'type' => $payload['type'],
      'id' => (int) $payload['id'],
      'changed' => isset($payload['changed']) ? (int) $payload['changed'] : NULL,
      'event' => $payload['event'] ?? NULL,
      'delivery_id' => $payload['deliveryId'],
      'received' => time(),
    ];
    $this->queueFactory->get(self::QUEUE_ID)->createItem($item);

    $this->logger->info('Queued @event event for @type @id from @source.', [
      '@event' => $item->event ?? 'unknown',
      '@type' => $item->type,
      '@id' => $item->id,
      '@source' => $item->source,
    ]);

    return new JsonResponse(['queued' => TRUE], Response::HTTP_ACCEPTED);
  }

  /**
   * Validate the request signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $body
   *   The raw request body.
   * @param string $source
   *   The remote source id.
   *
   * @return bool
   *   TRUE if the signature is valid, FALSE otherwise.
   */
  private function validateSignature(Request $request, string $body, string $source): bool {
    $remote_source = $this->getRemoteSource($source);
    $secret = $remote_source?->getRemoteRefreshWebhookSecret();
    if (empty($secret)) {
      $this->logger->warning('Remote refresh webhook called for @source before a secret was configured.', [
        '@source' => $source,
      ]);
      return FALSE;
    }

    $timestamp = $request->headers->get('X-NCMS-Timestamp');
    $signature = $request->headers->get('X-NCMS-Signature');
    if (!$timestamp || !$signature || !ctype_digit((string) $timestamp)) {
      return FALSE;
    }

    $ttl = $remote_source->getRemoteRefreshSignatureTtl();
    if (abs(time() - (int) $timestamp) > $ttl) {
      return FALSE;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    return hash_equals($expected, $signature);
  }

  /**
   * Get the maximum allowed webhook request body size.
   *
   * @return int
   *   The maximum body size in bytes.
   */
  private function getMaxBodySize(string $source): int {
    return $this->getRemoteSource($source)?->getRemoteRefreshMaxBodySize() ?? 4096;
  }

  /**
   * Claim a delivery id so the same signed request cannot be replayed.
   *
   * @param array $payload
   *   The validated payload.
   *
   * @return bool
   *   TRUE if this delivery id has not been seen before, FALSE otherwise.
   */
  private function claimDeliveryId(array $payload): bool {
    $remote_source = $this->getRemoteSource($payload['source']);
    $ttl = $remote_source?->getRemoteRefreshSignatureTtl() ?? 300;
    $store = $this->keyValueExpirableFactory->get('ghi_content_remote_refresh_deliveries');
    return $store->setWithExpireIfNotExists($payload['source'] . ':' . $payload['deliveryId'], TRUE, max(1, $ttl) + 60);
  }

  /**
   * Get the largest configured webhook request body size.
   *
   * @return int
   *   The maximum configured body size in bytes.
   */
  private function getConfiguredMaxBodySize(): int {
    $max_body_size = 4096;
    foreach (array_keys($this->remoteSourceManager->getDefinitions()) as $source) {
      $max_body_size = max($max_body_size, $this->getMaxBodySize($source));
    }
    return $max_body_size;
  }

  /**
   * Get a remote refresh source plugin instance.
   *
   * @param string $source
   *   The remote source id.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteRefreshSourceInterface|null
   *   The remote source if it supports remote refresh notifications.
   */
  private function getRemoteSource(string $source): ?RemoteRefreshSourceInterface {
    if (isset($this->remoteSources[$source])) {
      return $this->remoteSources[$source];
    }
    if (!array_key_exists($source, $this->remoteSourceManager->getDefinitions())) {
      return NULL;
    }
    $remote_source = $this->remoteSourceManager->createInstance($source);
    if (!$remote_source instanceof RemoteRefreshSourceInterface) {
      return NULL;
    }
    $this->remoteSources[$source] = $remote_source;
    return $this->remoteSources[$source];
  }

  /**
   * Validate the decoded payload.
   *
   * @param mixed $payload
   *   The decoded payload.
   *
   * @return string[]
   *   Validation errors.
   */
  private function validatePayload($payload): array {
    $errors = [];
    if (!is_array($payload)) {
      return ['Payload must be an object.'];
    }
    if (empty($payload['source']) || !is_string($payload['source']) || !$this->getRemoteSource($payload['source'])) {
      $errors[] = 'Unsupported source.';
    }
    if (!in_array($payload['type'] ?? NULL, ['article', 'document'], TRUE)) {
      $errors[] = 'Unsupported content type.';
    }
    if (empty($payload['id']) || !is_numeric($payload['id'])) {
      $errors[] = 'Missing content id.';
    }
    if (empty($payload['event']) || !is_string($payload['event']) || !in_array($payload['event'], [
      'saved',
      'trashed',
      'deleted',
      'ping',
    ], TRUE)) {
      $errors[] = 'Unsupported event.';
    }
    if (empty($payload['deliveryId']) || !is_string($payload['deliveryId']) || !Uuid::isValid($payload['deliveryId'])) {
      $errors[] = 'Missing delivery id.';
    }
    return $errors;
  }

}
