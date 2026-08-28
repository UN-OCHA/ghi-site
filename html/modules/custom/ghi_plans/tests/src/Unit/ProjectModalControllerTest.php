<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_plans\Controller\ProjectModalController;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the legacy project asset proxy.
 *
 * @group ghi_plans
 *
 * @coversDefaultClass \Drupal\ghi_plans\Controller\ProjectModalController
 */
class ProjectModalControllerTest extends UnitTestCase {

  /**
   * Tests that the proxy serves validated image bytes with safe headers.
   *
   * @covers ::buildLegacyProjectAsset
   */
  public function testBuildLegacyProjectAssetServesValidatedImage(): void {
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', TRUE);
    $http_client = $this->mockHttpClient(new Psr7Response(Response::HTTP_OK, [
      'Content-Type' => 'text/html',
    ], $image));
    $controller = $this->createController('_assets/images/project.png', $http_client);

    $response = $controller->buildLegacyProjectAsset();

    $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertSame($image, $response->getContent());
    $this->assertSame('image/png', $response->headers->get('Content-Type'));
    $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    $this->assertSame("default-src 'none'; style-src 'unsafe-inline'; sandbox", $response->headers->get('Content-Security-Policy'));
    $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
    $this->assertFalse($http_client->options['allow_redirects']);
  }

  /**
   * Tests that active or out-of-scope paths are rejected before fetching.
   *
   * @param string $path
   *   The requested proxy path.
   *
   * @dataProvider disallowedAssetPathProvider
   *
   * @covers ::buildLegacyProjectAsset
   */
  public function testBuildLegacyProjectAssetRejectsDisallowedPath(string $path): void {
    $http_client = $this->mockHttpClient(new Psr7Response(Response::HTTP_OK));
    $controller = $this->createController($path, $http_client);

    $response = $controller->buildLegacyProjectAsset();

    $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    $this->assertSame(0, $http_client->requestCount);
  }

  /**
   * Provides active and out-of-scope asset paths.
   *
   * @return array<string, array{string}>
   *   The test cases.
   */
  public function disallowedAssetPathProvider(): array {
    return [
      'project HTML' => ['projects/123.html'],
      'JavaScript' => ['_assets/project.js'],
      'image outside asset directory' => ['projects/image.png'],
    ];
  }

  /**
   * Tests that SVG assets are sanitized before being served.
   *
   * @covers ::buildLegacyProjectAsset
   */
  public function testBuildLegacyProjectAssetSanitizesSvg(): void {
    $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert('unsafe')">
  <style>.icon { fill: #5091cd; }</style>
  <script>alert('unsafe')</script>
  <image href="https://malicious.example.test/tracker.png" />
  <use href="#safe-path" />
  <path class="icon" d="M0 0h10v10H0z" />
  <path id="safe-path" d="M0 0h1v1H0z" />
</svg>
SVG;
    $http_client = $this->mockHttpClient(new Psr7Response(Response::HTTP_OK, [
      'Content-Type' => 'text/html',
    ], $svg));
    $controller = $this->createController('_assets/icons/project.svg', $http_client);

    $response = $controller->buildLegacyProjectAsset();

    $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('<style>.icon { fill: #5091cd; }</style>', $response->getContent());
    $this->assertStringContainsString('<path class="icon"', $response->getContent());
    $this->assertStringContainsString('href="#safe-path"', $response->getContent());
    $this->assertStringNotContainsString('script', $response->getContent());
    $this->assertStringNotContainsString('onload', $response->getContent());
    $this->assertStringNotContainsString('malicious.example.test', $response->getContent());
  }

  /**
   * Tests that malformed SVG content is rejected.
   *
   * @covers ::buildLegacyProjectAsset
   */
  public function testBuildLegacyProjectAssetRejectsInvalidSvg(): void {
    $http_client = $this->mockHttpClient(new Psr7Response(Response::HTTP_OK, [
      'Content-Type' => 'image/svg+xml',
    ], '<svg><path></svg>'));
    $controller = $this->createController('_assets/icons/project.svg', $http_client);

    $response = $controller->buildLegacyProjectAsset();

    $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * Tests that an image extension cannot disguise active content.
   *
   * @covers ::buildLegacyProjectAsset
   */
  public function testBuildLegacyProjectAssetRejectsInvalidImageContent(): void {
    $http_client = $this->mockHttpClient(new Psr7Response(Response::HTTP_OK, [
      'Content-Type' => 'image/png',
    ], '<script>alert("unsafe")</script>'));
    $controller = $this->createController('_assets/images/project.png', $http_client);

    $response = $controller->buildLegacyProjectAsset();

    $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * Create a controller for the requested asset path.
   *
   * @param string $asset_path
   *   The requested asset path.
   * @param object $http_client
   *   The mocked HTTP client.
   *
   * @return \Drupal\ghi_plans\Controller\ProjectModalController
   *   The configured controller.
   */
  private function createController(string $asset_path, object $http_client): ProjectModalController {
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->getConfigFactoryStub([
      'ghi_plans.legacy_projects' => [
        'base_url' => 'https://legacy-projects.example.test/export',
      ],
    ]));
    \Drupal::setContainer($container);

    $request_stack = new RequestStack();
    $request_stack->push(new Request([
      'path' => $asset_path,
    ]));

    $controller = new ProjectModalController();
    $controller->httpClient = $http_client;
    $controller->requestStack = $request_stack;
    return $controller;
  }

  /**
   * Create a mocked HTTP client that returns the given response.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   The upstream response.
   *
   * @return object
   *   The mocked HTTP client and its recorded request state.
   */
  private function mockHttpClient(Psr7Response $response): object {
    return new class($response) {

      /**
       * The number of requests made.
       */
      public int $requestCount = 0;

      /**
       * The last request options.
       *
       * @var array<string, mixed>
       */
      public array $options = [];

      /**
       * Construct the client.
       *
       * @param \GuzzleHttp\Psr7\Response $response
       *   The response to return.
       */
      public function __construct(private readonly Psr7Response $response) {}

      /**
       * Return the configured response and record the request.
       *
       * @param string $url
       *   The requested URL.
       * @param array<string, mixed> $options
       *   The request options.
       *
       * @return \GuzzleHttp\Psr7\Response
       *   The configured response.
       */
      public function get(string $url, array $options): Psr7Response {
        $this->requestCount++;
        $this->options = $options;
        return $this->response;
      }

    };
  }

}
