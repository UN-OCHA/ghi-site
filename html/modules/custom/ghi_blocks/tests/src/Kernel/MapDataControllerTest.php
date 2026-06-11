<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\ghi_blocks\Controller\MapDataController;
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

}
