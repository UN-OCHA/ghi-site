<?php

namespace Drupal\Tests\ghi_content\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests resolving content nodes from paths.
 */
#[Group('ghi_content')]
class ContentPathTraitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    drupal_static_reset('getNodeByUrlAlias');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    drupal_static_reset('getNodeByUrlAlias');
    parent::tearDown();
  }

  /**
   * Tests that routes without a node do not request entity storage.
   */
  #[DataProvider('providerNonNodeRoutes')]
  public function testNonNodeRoutes(string $alias, string $route_name, array $parameters): void {
    $container = new ContainerBuilder();
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->method('getPathByAlias')->willReturnArgument(0);
    $container->set('path_alias.manager', $alias_manager);
    $validator = $this->createMock(PathValidatorInterface::class);
    $validator->method('getUrlIfValidWithoutAccessCheck')->willReturn(Url::fromRoute($route_name, $parameters));
    $container->set('path.validator', $validator);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->never())->method('getStorage');
    $container->set('entity_type.manager', $entity_type_manager);
    $repository = $this->createMock(RedirectRepository::class);
    $repository->method('findBySourcePath')->willReturn([]);
    $container->set('redirect.repository', $repository);
    \Drupal::setContainer($container);

    $this->assertNull($this->createConsumer()->getNodeByUrlAlias($alias));
  }

  /**
   * Provides routes without a node parameter.
   */
  public static function providerNonNodeRoutes(): array {
    return [
      'empty alias' => ['', '<none>', []],
      'parameterless route' => ['/user/login', 'user.login', []],
      'other entity type' => ['/user/1', 'entity.user.canonical', ['user' => 1]],
    ];
  }

  /**
   * Tests that destination parameters are not mistaken for document paths.
   */
  public function testDocumentInDestination(): void {
    $container = new ContainerBuilder();
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->never())->method('getPathByAlias');
    $container->set('path_alias.manager', $alias_manager);
    \Drupal::setContainer($container);

    $this->assertNull($this->createConsumer()->getDocumentNodeFromPath('/article/example/edit?destination=/document/report/article/example'));
  }

  /**
   * Tests that a document/article path still resolves its document.
   */
  public function testDocumentPath(): void {
    $document = $this->createMock(Document::class);
    $this->mockNodeLookup('/document/report', $document);

    $this->assertSame($document, $this->createConsumer()->getDocumentNodeFromPath('/document/report/article/example?destination=/user'));
  }

  /**
   * Tests that redirects to node aliases are still followed.
   */
  public function testRedirect(): void {
    $node = $this->createMock(NodeInterface::class);
    $container = $this->mockNodeLookup('/article/example', $node);
    $redirect = $this->createMock(Redirect::class);
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/article/example');
    $redirect->method('getRedirectUrl')->willReturn($url);
    $repository = $this->createMock(RedirectRepository::class);
    $repository->expects($this->once())->method('findBySourcePath')->with('old-article')->willReturn([$redirect]);
    $container->set('redirect.repository', $repository);

    $this->assertSame($node, $this->createConsumer()->getNodeByUrlAlias('/old-article'));
  }

  /**
   * Mocks alias resolution and storage for a single node.
   */
  private function mockNodeLookup(string $alias, NodeInterface $node): ContainerBuilder {
    $container = new ContainerBuilder();
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->method('getPathByAlias')->willReturnCallback(static fn ($path) => $path === $alias ? '/node/42' : $path);
    $container->set('path_alias.manager', $alias_manager);
    $validator = $this->createMock(PathValidatorInterface::class);
    $validator->method('getUrlIfValidWithoutAccessCheck')->willReturnCallback(static fn ($path) => $path === 'node/42' ? Url::fromRoute('entity.node.canonical', ['node' => 42]) : FALSE);
    $container->set('path.validator', $validator);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(42)->willReturn($node);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())->method('getStorage')->with('node')->willReturn($storage);
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
    return $container;
  }

  /**
   * Creates a consumer exposing the trait's path lookup methods.
   */
  private function createConsumer(): object {
    return new class() {
      use ContentPathTrait {
        getNodeByUrlAlias as public;
        getDocumentNodeFromPath as public;
      }
    };
  }

}
