<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\Routing\Router;
use Drupal\ghi_blocks\Controller\AjaxBlockController;
use Drupal\ghi_blocks\Controller\MapDataController;
use Drupal\ghi_blocks\Controller\PreviewMapModalDataController;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the lazy map data controller.
 *
 * @group ghi_blocks
 */
class MapDataControllerTest extends KernelTestBase {

  use UserCreationTrait;
  use PrivateAccessorTrait;

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
   * Tests that map switchers use unsaved settings and retain editor controls.
   */
  public function testMapSwitcherUsesUnsavedLayout(): void {
    $uri = '/layout_builder/add/block/overrides/node.1/0/content/plan_attachment_map';
    $configuration = ['id' => 'plan_attachment_map', 'label' => 'Unsaved map'];
    $block = $this->createMock(PlanAttachmentMap::class);
    $block->method('getConfiguration')->willReturn($configuration);
    $block->method('getContextDefinitions')->willReturn([]);
    $block->expects($this->once())->method('buildContent')->willReturn(['#markup' => 'Unsaved attachment selection']);
    $block->expects($this->never())->method('getPageNode');
    $component = $this->createMock(SectionComponent::class);
    $component->method('getPluginId')->willReturn('plan_attachment_map');
    $component->method('getUuid')->willReturn('unsaved-map');
    $component->method('getPlugin')->willReturn($block);
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getSections')->willReturn([new Section('layout_onecol', [], [$component])]);
    $storage->method('getContexts')->willReturn([]);
    $router = $this->createMock(Router::class);
    $router->method('match')->with($uri)->willReturn(['section_storage' => $storage]);
    $manager = $this->createMock(BlockManager::class);
    $manager->expects($this->once())->method('createInstance')->with('plan_attachment_map', $configuration + ['uuid' => 'unsaved-map'])->willReturn($block);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkRequest')->willReturn(TRUE);
    $this->container->set('router.no_access_checks', $router);
    $this->container->set('plugin.manager.block', $manager);
    $this->container->set('access_manager', $access_manager);
    $request = Request::create('/load-block/plan_attachment_map/unsaved-map', 'GET', [
      'current_uri' => $uri,
      '_wrapper_format' => 'drupal_ajax',
    ]);
    $this->container->get('request_stack')->push($request);

    $controller = AjaxBlockController::create($this->container);
    $response = $controller->loadBlock('plan_attachment_map', 'unsaved-map');
    $command = $response->getCommands()[0];
    $this->assertSame('html', $command['method']);
    $this->assertSame('.ghi-block-unsaved-map > .block-content', $command['selector']);
    $this->assertSame('Unsaved attachment selection', (string) $command['data']);
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  /**
   * Tests that Layout Builder previews omit export-only PNG markup.
   */
  public function testLayoutPreviewOmitsPngHeader(): void {
    require_once DRUPAL_ROOT . '/modules/custom/hpc_downloads/hpc_downloads.module';
    $router = $this->createMock(Router::class);
    $router->expects($this->never())->method('match');
    $this->container->set('router.no_access_checks', $router);
    $variables = [
      'elements' => ['#in_preview' => TRUE],
      'plugin_id' => 'plan_attachment_map',
      'configuration' => ['uuid' => 'unsaved-map'],
    ];

    hpc_downloads_preprocess_block($variables);

    $this->assertArrayNotHasKey('snap_png_header', $variables);
  }

  /**
   * Tests that unsaved map responses still check access and cannot be cached.
   */
  public function testUnsavedLayoutAccessIsUncacheable(): void {
    $storage = $this->createMock(SectionStorageInterface::class);
    $router = $this->createMock(Router::class);
    $router->method('matchRequest')->willReturn(['section_storage' => $storage]);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects($this->once())->method('checkRequest')->with($this->isInstanceOf(Request::class), $this->container->get('current_user'), TRUE)->willReturn(AccessResult::forbidden());
    $this->container->set('router.no_access_checks', $router);
    $this->container->set('access_manager', $access_manager);
    $this->container->get('request_stack')->push(Request::create('/map-data/test/uuid'));

    $controller = MapDataController::create($this->container);
    $access = $this->callPrivateMethod($controller, 'checkUriAccess', ['/layout_builder/import/block/overrides/node.1/0/content']);
    $this->assertTrue($access->isForbidden());
    $this->assertSame(0, $access->getCacheMaxAge());
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
