<?php

namespace Drupal\Tests\ghi_content\Unit\Controller;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\ghi_content\Controller\RemoteRefreshController;
use Drupal\ghi_content\RemoteSource\RemoteRefreshSourceInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the remote refresh webhook controller.
 *
 * @group ghi_content
 */
class RemoteRefreshControllerTest extends UnitTestCase {

  /**
   * A valid delivery id.
   */
  const DELIVERY_ID = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

  /**
   * Tests that a valid signed notification is queued.
   */
  public function testValidRequestQueuesRefresh() {
    $secret = 'local-refresh-secret';
    $payload = [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'changed' => 1710000000,
      'event' => 'saved',
      'deliveryId' => self::DELIVERY_ID,
    ];
    $body = json_encode($payload);
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
      ->method('createItem')
      ->with($this->callback(function ($item) {
        return $item->source === 'hpc_content_module'
          && $item->type === 'article'
          && $item->id === 123
          && $item->changed === 1710000000
          && $item->event === 'saved'
          && $item->delivery_id === self::DELIVERY_ID;
      }));

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    $this->assertSame('{"queued":true}', $response->getContent());
  }

  /**
   * Tests that any discovered remote source can send refresh notifications.
   */
  public function testValidRequestForDiscoveredSourceQueuesRefresh() {
    $secret = 'secondary-secret';
    $payload = [
      'source' => 'secondary_source',
      'type' => 'document',
      'id' => 456,
      'event' => 'saved',
      'deliveryId' => self::DELIVERY_ID,
    ];
    $body = json_encode($payload);
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
      ->method('createItem')
      ->with($this->callback(function ($item) {
        return $item->source === 'secondary_source'
          && $item->type === 'document'
          && $item->id === 456;
      }));

    $controller = $this->createController($secret, $queue, 'secondary_source');
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
  }

  /**
   * Tests that trashed notifications are queued.
   */
  public function testTrashedRequestQueuesRefresh() {
    $secret = 'local-refresh-secret';
    $payload = [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'trashed',
      'deliveryId' => self::DELIVERY_ID,
    ];
    $body = json_encode($payload);
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
      ->method('createItem')
      ->with($this->callback(function ($item) {
        return $item->event === 'trashed';
      }));

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
  }

  /**
   * Tests that duplicate deliveries are not queued again.
   */
  public function testDuplicateDeliveryIsNotQueued() {
    $secret = 'local-refresh-secret';
    $payload = [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'deleted',
      'deliveryId' => self::DELIVERY_ID,
    ];
    $body = json_encode($payload);
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue, 'hpc_content_module', FALSE);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    $this->assertSame('{"queued":false}', $response->getContent());
  }

  /**
   * Tests that ping requests are validated without queueing.
   */
  public function testPingRequestIsNotQueued() {
    $secret = 'local-refresh-secret';
    $payload = [
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 1,
      'event' => 'ping',
      'deliveryId' => self::DELIVERY_ID,
    ];
    $body = json_encode($payload);
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    $this->assertSame('{"checked":true}', $response->getContent());
  }

  /**
   * Tests that delivery ids are required.
   */
  public function testMissingDeliveryIdIsRejected() {
    $secret = 'local-refresh-secret';
    $body = '{"source":"hpc_content_module","type":"article","id":123,"event":"saved"}';
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  /**
   * Tests that event is required.
   */
  public function testMissingEventIsRejected() {
    $secret = 'local-refresh-secret';
    $body = '{"source":"hpc_content_module","type":"article","id":123,"deliveryId":"aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"}';
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  /**
   * Tests that an invalid signature is rejected before queueing.
   */
  public function testInvalidSignatureIsRejected() {
    $body = '{"source":"hpc_content_module","type":"article","id":123,"event":"saved","deliveryId":"aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"}';
    $request = Request::create('/', 'POST', [], [], [], [], $body);
    $request->headers->set('X-NCMS-Timestamp', (string) time());
    $request->headers->set('X-NCMS-Signature', 'sha256=invalid');

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController('local-refresh-secret', $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
  }

  /**
   * Tests that oversized requests are rejected before queueing.
   */
  public function testOversizedRequestIsRejected() {
    $request = Request::create('/', 'POST', [], [], [], [], str_repeat('x', 4097));

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController('local-refresh-secret', $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
  }

  /**
   * Tests that old signed requests are rejected.
   */
  public function testExpiredSignatureIsRejected() {
    $secret = 'local-refresh-secret';
    $body = '{"source":"hpc_content_module","type":"article","id":123,"event":"saved","deliveryId":"aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"}';
    $timestamp = (string) (time() - 301);
    $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    $request = Request::create('/', 'POST', [], [], [], [], $body);
    $request->headers->set('X-NCMS-Timestamp', $timestamp);
    $request->headers->set('X-NCMS-Signature', $signature);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
  }

  /**
   * Tests that invalid payloads are rejected after signature verification.
   */
  public function testInvalidPayloadIsRejected() {
    $secret = 'local-refresh-secret';
    $body = '{"source":"unknown","type":"article","id":123,"deliveryId":"aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"}';
    $request = $this->createSignedRequest($body, $secret);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->never())->method('createItem');

    $controller = $this->createController($secret, $queue);
    $response = $controller->receive($request);

    $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  /**
   * Create the controller under test.
   *
   * @param string $secret
   *   The configured shared secret.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue mock.
   * @param string $source
   *   The discovered remote source id.
   * @param bool $claim_delivery
   *   Whether the delivery id can be claimed.
   *
   * @return \Drupal\ghi_content\Controller\RemoteRefreshController
   *   The controller.
   */
  private function createController(string $secret, QueueInterface $queue, string $source = 'hpc_content_module', bool $claim_delivery = TRUE): RemoteRefreshController {
    $queue_factory = $this->createMock(QueueFactory::class);
    $queue_factory->method('get')
      ->with(RemoteRefreshController::QUEUE_ID)
      ->willReturn($queue);

    $remote_source = $this->createMock(RemoteRefreshSourceInterface::class);
    $remote_source->method('getRemoteRefreshWebhookSecret')->willReturn($secret);
    $remote_source->method('getRemoteRefreshSignatureTtl')->willReturn(300);
    $remote_source->method('getRemoteRefreshMaxBodySize')->willReturn(4096);

    $remote_source_manager = $this->createMock(RemoteSourceManager::class);
    $remote_source_manager->method('getDefinitions')->willReturn([
      $source => ['id' => $source],
    ]);
    $remote_source_manager->method('createInstance')
      ->with($source)
      ->willReturn($remote_source);

    $delivery_store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $delivery_store->method('setWithExpireIfNotExists')->willReturn($claim_delivery);

    $key_value_expirable_factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $key_value_expirable_factory->method('get')
      ->with('ghi_content_remote_refresh_deliveries')
      ->willReturn($delivery_store);

    return new RemoteRefreshController($queue_factory, $remote_source_manager, $key_value_expirable_factory, new NullLogger());
  }

  /**
   * Create a signed request for the given body.
   *
   * @param string $body
   *   The raw request body.
   * @param string $secret
   *   The shared secret.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The signed request.
   */
  private function createSignedRequest(string $body, string $secret): Request {
    $timestamp = (string) time();
    $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    $request = Request::create('/', 'POST', [], [], [], [], $body);
    $request->headers->set('X-NCMS-Timestamp', $timestamp);
    $request->headers->set('X-NCMS-Signature', $signature);
    return $request;
  }

}
