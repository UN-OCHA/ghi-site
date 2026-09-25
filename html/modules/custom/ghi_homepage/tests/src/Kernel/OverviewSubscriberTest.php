<?php

namespace Drupal\Tests\ghi_homepage\Kernel;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests overview year validation and cache invalidation.
 */
#[Group('ghi_homepage')]
class OverviewSubscriberTest extends KernelTestBase {

  use FieldTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'node', 'field', 'text', 'filter', 'token',
    'path_alias', 'pathauto', 'page_manager', 'ghi_homepage',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);
    NodeType::create(['type' => Homepage::BUNDLE])->save();
    $this->createField('node', Homepage::BUNDLE, 'integer', 'field_year', 'Year');

    Page::create([
      'id' => 'homepage',
      'label' => 'Homepage',
      'path' => '/overview/{year}',
      'parameters' => [
        'year' => ['type' => 'integer', 'optional' => TRUE],
      ],
    ])->save();
    PageVariant::create([
      'id' => 'homepage_test',
      'label' => 'Homepage',
      'variant' => 'http_status_code',
      'page' => 'homepage',
    ])->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests missing, published and unpublished years without clearing caches.
   */
  public function testPublicationChanges(): void {
    $cache = $this->container->get('cache.default');
    $response = $this->getOverviewResponse('/overview/2099');
    $this->assertSame(404, $response->getStatusCode());
    $this->assertContains('node_list:homepage', $response->getCacheableMetadata()->getCacheTags());
    $this->assertContains('url.path', $response->getCacheableMetadata()->getCacheContexts());
    $cache->set('overview', $response, tags: $response->getCacheableMetadata()->getCacheTags());
    $this->assertNotFalse($cache->get('overview'));

    $homepage = Node::create([
      'type' => Homepage::BUNDLE,
      'title' => '2099',
      'field_year' => 2099,
      'status' => TRUE,
    ]);
    $homepage->save();
    $this->assertFalse($cache->get('overview'));
    $response = $this->getOverviewResponse('/overview/2099');
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(404, $this->getOverviewResponse('/overview/2100')->getStatusCode());
    $this->assertSame(404, $this->getOverviewResponse('/overview/2099invalid')->getStatusCode());
    $cache->set('overview', $response, tags: $response->getCacheableMetadata()->getCacheTags());

    $homepage->setUnpublished()->save();
    $this->assertFalse($cache->get('overview'));
    $response = $this->getOverviewResponse('/overview/2099');
    $this->assertSame(404, $response->getStatusCode());
    $cache->set('overview', $response, tags: $response->getCacheableMetadata()->getCacheTags());

    $homepage->setPublished()->save();
    $this->assertFalse($cache->get('overview'));
    $response = $this->getOverviewResponse('/overview/2099');
    $this->assertSame(200, $response->getStatusCode());
    $cache->set('overview', $response, tags: $response->getCacheableMetadata()->getCacheTags());

    $homepage->delete();
    $this->assertFalse($cache->get('overview'));
    $this->assertSame(404, $this->getOverviewResponse('/overview/2099')->getStatusCode());
  }

  /**
   * Tests that a missing year and unrelated routes are left alone.
   */
  public function testOtherRequests(): void {
    $this->assertSame(200, $this->getOverviewResponse('/overview')->getStatusCode());
    $response = $this->getOverviewResponse('/user/login');
    $this->assertSame(200, $response->getStatusCode());
    $this->assertNotContains('node_list:homepage', $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * Gets a response from the subscriber using real Page Manager routing.
   */
  private function getOverviewResponse(string $path): CacheableResponse {
    $request = Request::create($path);
    $request->attributes->add($this->container->get('router.no_access_checks')->matchRequest($request));
    $subscriber = $this->container->get('ghi_homepage.overview_subscriber');
    $kernel = $this->container->get('http_kernel');
    $response = new CacheableResponse();
    try {
      $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
    }
    catch (CacheableNotFoundHttpException $e) {
      $response->setStatusCode($e->getStatusCode());
      $response->addCacheableDependency($e);
    }
    $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
    return $response;
  }

}
