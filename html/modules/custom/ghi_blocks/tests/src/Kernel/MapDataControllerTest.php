<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\ghi_blocks\Controller\MapDataController;
use Drupal\ghi_blocks\Controller\PreviewMapModalDataController;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the lazy map data controller.
 *
 * @group ghi_blocks
 */
class MapDataControllerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'layout_builder',
    'layout_discovery',
    'migrate',
    'hpc_api',
    'ghi_form_elements',
    'ghi_sections',
    'ghi_blocks',
    'ghi_base_objects',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Tests that inaccessible page URIs do not return map payloads.
   */
  public function testDataDeniesInaccessibleCurrentUri(): void {
    $request = Request::create('/map-data/plan_attachment_map/block_uuid', 'GET', [
      'current_uri' => '/admin',
      'map_id' => 'test-map',
    ]);
    $this->container->get('request_stack')->push($request);

    $controller = MapDataController::create($this->container);
    $response = $controller->data('plan_attachment_map', 'block_uuid');

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame([], $response->getCommands());
    $this->assertContains('user.permissions', $response->getCacheableMetadata()->getCacheContexts());
  }

  /**
   * Tests that inaccessible page URIs do not return map fragments.
   */
  public function testDataFragmentDeniesInaccessibleCurrentUri(): void {
    $request = Request::create('/map-data/plan_attachment_map/block_uuid/fragment', 'GET', [
      'current_uri' => '/admin',
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
    ]);
    $this->container->get('request_stack')->push($request);

    $controller = MapDataController::create($this->container);
    $response = $controller->dataFragment('plan_attachment_map', 'block_uuid');

    $this->assertSame(403, $response->getStatusCode());
    $this->assertContains('user.permissions', $response->getCacheableMetadata()->getCacheContexts());
  }

  /**
   * Tests that inaccessible page URIs do not return map modal data.
   */
  public function testModalDataDeniesInaccessibleCurrentUri(): void {
    $request = Request::create('/map-data/plan_attachment_map/block_uuid/modal', 'GET', [
      'current_uri' => '/admin',
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
      'object_id' => '10',
    ]);
    $this->container->get('request_stack')->push($request);

    $controller = MapDataController::create($this->container);
    $response = $controller->modalData('plan_attachment_map', 'block_uuid');

    $this->assertSame(403, $response->getStatusCode());
    $this->assertContains('user.permissions', $response->getCacheableMetadata()->getCacheContexts());
  }

  /**
   * Tests that modal data can be loaded for maps without tabs.
   */
  public function testPreviewModalDataUsesDefaultDataIndex(): void {
    $token = 'test-token';
    $store = $this->container->get('keyvalue.expirable')
      ->get(MapModalContent::CONFIGURATION_PREVIEW_COLLECTION);
    $store->setWithExpire(MapModalContent::buildStoreKey($token, MapModalContent::DEFAULT_DATA_INDEX, MapModalContent::DEFAULT_VARIANT_ID), [
      'uid' => (int) $this->container->get('current_user')->id(),
      'modal_contents' => [
        '10' => ['content' => '<p>Presence modal</p>'],
      ],
    ], MapModalContent::CONFIGURATION_PREVIEW_TTL);

    $request = Request::create('/map-preview-modal-data/' . $token, 'GET', [
      'object_id' => '10',
    ]);
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    try {
      $controller = PreviewMapModalDataController::create($this->container);
      $response = $controller->data($token);
    }
    finally {
      $request_stack->pop();
    }

    $this->assertSame(['content' => '<p>Presence modal</p>'], json_decode($response->getContent(), TRUE));
    $cache_control = $response->headers->get('Cache-Control');
    $this->assertStringContainsString('private', $cache_control);
    $this->assertStringContainsString('no-store', $cache_control);
    $this->assertStringContainsString('max-age=0', $cache_control);
  }

}
