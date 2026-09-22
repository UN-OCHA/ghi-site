<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\Routing\Router;
use Drupal\ghi_blocks\Controller\AjaxBlockController;
use Drupal\ghi_blocks\Controller\BlockPreviewController;
use Drupal\ghi_blocks\Controller\MapDataController;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Preview\BlockPreviewStateManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Tests the lazy map data and block preview controllers.
 */
#[Group('ghi_blocks')]
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
    'ghi_blocks_test',
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
   * Tests storing, restoring, and reusing block preview state.
   */
  public function testPreviewStateStorageAndRestoration(): void {
    $block = $this->createPreviewBlock(['label' => 'Unsaved preview']);
    $block->setContextValue('year', 2026);
    $account = $this->container->get('entity_type.manager')->getStorage('user')->load($this->container->get('current_user')->id());
    $block->setContextValue('user', $account);

    $manager = $this->getPreviewStateManager();
    $token = $manager->store($block);
    $this->assertSame($token, $manager->store($block));

    $manager->storeResource($token, 'test', 'resource', ['value' => 'stored']);
    $this->assertSame(['value' => 'stored'], $manager->getResource($token, 'test', 'resource'));

    $restored = $manager->restore($token);
    $this->assertSame('Unsaved preview', $restored->getConfiguration()['label']);
    $this->assertSame('/plan/1135/logframe', $restored->getCurrentUri());
    $this->assertSame(2026, $restored->getContextValue('year'));
    $this->assertSame($account->id(), $restored->getContextValue('user')->id());
    $this->assertSame($token, $restored->buildContent()['#markup']);
  }

  /**
   * Tests preview state ownership.
   */
  public function testPreviewStateOwnership(): void {
    $manager = $this->getPreviewStateManager();
    $token = $manager->store($this->createPreviewBlock());
    $owner = $this->container->get('current_user')->getAccount();
    $this->container->get('current_user')->setAccount($this->createUser());

    try {
      $this->expectException(AccessDeniedHttpException::class);
      $manager->restore($token);
    }
    finally {
      $this->container->get('current_user')->setAccount($owner);
    }
  }

  /**
   * Tests that missing or expired preview state is rejected.
   */
  public function testMissingPreviewState(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->getPreviewStateManager()->restore('missing-or-expired-token');
  }

  /**
   * Tests that modal resources use the block preview token.
   */
  public function testPreviewModalResourceUsesBlockToken(): void {
    $manager = $this->getPreviewStateManager();
    $token = $manager->store($this->createPreviewBlock());
    $resource_key = MapModalContent::buildResourceKey('test-map', MapModalContent::DEFAULT_DATA_INDEX, MapModalContent::DEFAULT_VARIANT_ID);
    $manager->storeResource($token, MapModalContent::RESOURCE_NAMESPACE, $resource_key, [
      '10' => ['content' => '<p>Presence modal</p>'],
    ]);

    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/block-preview/' . $token . '/map-data/modal', 'GET', [
      'map_id' => 'test-map',
      'data_index' => MapModalContent::DEFAULT_DATA_INDEX,
      'object_id' => '10',
    ]));
    try {
      $response = BlockPreviewController::create($this->container)->mapModalData($token);
    }
    finally {
      $request_stack->pop();
    }

    $this->assertSame(['content' => '<p>Presence modal</p>'], json_decode($response->getContent(), TRUE));
    $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
  }

  /**
   * Tests lazy map fragments built from unsaved preview block state.
   */
  public function testBlockPreviewMapFragments(): void {
    $preview_state_token = $this->getPreviewStateManager()->store($this->createPreviewBlock());
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/block-preview/' . $preview_state_token . '/map-data/fragment', 'GET', [
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
      'variant_id' => '2514',
    ]));
    try {
      $response = BlockPreviewController::create($this->container)->mapDataFragment($preview_state_token);
    }
    finally {
      $request_stack->pop();
    }

    $this->assertSame([
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
      'variant_id' => '2514',
      'current_uri' => '/plan/1135/logframe',
    ], json_decode($response->getContent(), TRUE));
    $this->assertPrivateNoStore($response->headers->get('Cache-Control'));

    $request_stack->push(Request::create('/block-preview/' . $preview_state_token . '/map-data/modal', 'GET', [
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
      'object_id' => '10',
    ]));
    try {
      $response = BlockPreviewController::create($this->container)->mapModalData($preview_state_token);
    }
    finally {
      $request_stack->pop();
    }

    $this->assertSame([
      'map_id' => 'test-map',
      'data_index' => 'people-targeted-0',
      'object_id' => '10',
      'variant_id' => NULL,
      'current_uri' => '/plan/1135/logframe',
    ], json_decode($response->getContent(), TRUE));
    $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
  }

  /**
   * Tests reloading a block interaction from unsaved preview state.
   */
  public function testBlockPreviewReload(): void {
    $preview_state_token = $this->getPreviewStateManager()->store($this->createPreviewBlock());
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/block-preview/' . $preview_state_token . '/reload'));
    try {
      $response = BlockPreviewController::create($this->container)->reload($preview_state_token);
    }
    finally {
      $request_stack->pop();
    }

    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('replaceWith', $commands[0]['method']);
    $this->assertSame('.ghi-block-test-block-uuid', $commands[0]['selector']);
    $this->assertStringContainsString($preview_state_token, (string) $commands[0]['data']);
    $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
  }

  /**
   * Tests that preview tokens cannot leak through the block content cache.
   */
  public function testPreviewTokensAreCacheIsolated(): void {
    $manager = $this->getPreviewStateManager();
    $token_a = $manager->store($this->createPreviewBlock());
    $token_b = $manager->store($this->createPreviewBlock());
    $this->assertNotSame($token_a, $token_b);
    $this->assertSame(0, $manager->restore($token_a)->build()['#cache']['max-age']);

    $response_a = BlockPreviewController::create($this->container)->preview($token_a);
    $response_b = BlockPreviewController::create($this->container)->preview($token_b);
    $markup_a = (string) $response_a->getCommands()[0]['data'];
    $markup_b = (string) $response_b->getCommands()[0]['data'];

    $this->assertStringContainsString($token_a, $markup_a);
    $this->assertStringNotContainsString($token_b, $markup_a);
    $this->assertStringContainsString($token_b, $markup_b);
    $this->assertStringNotContainsString($token_a, $markup_b);
  }

  /**
   * Tests that the superseded standalone modal route is absent.
   */
  public function testLegacyPreviewModalRouteIsRemoved(): void {
    $this->expectException(RouteNotFoundException::class);
    $this->container->get('router.route_provider')->getRouteByName('ghi_blocks.map_preview_modal_data');
  }

  /**
   * Create a preview test block with stable identity and state.
   */
  private function createPreviewBlock(array $configuration = []): GHIBlockBase {
    $configuration += [
      'id' => 'ghi_blocks_lazy_map_preview_test',
      'uuid' => 'test-block-uuid',
      'label' => 'Preview block',
      'provider' => 'ghi_blocks_test',
      'hpc' => [],
    ];
    $block = $this->container->get('plugin.manager.block')->createInstance('ghi_blocks_lazy_map_preview_test', $configuration);
    $this->assertInstanceOf(GHIBlockBase::class, $block);
    $block->setCurrentUri('/plan/1135/logframe');
    return $block;
  }

  /**
   * Get the block preview state manager.
   */
  private function getPreviewStateManager(): BlockPreviewStateManager {
    return $this->container->get('ghi_blocks.preview_state_manager');
  }

  /**
   * Assert private, non-cacheable preview response headers.
   */
  private function assertPrivateNoStore(?string $cache_control): void {
    $this->assertStringContainsString('private', $cache_control);
    $this->assertStringContainsString('no-store', $cache_control);
    $this->assertStringContainsString('max-age=0', $cache_control);
  }

}
