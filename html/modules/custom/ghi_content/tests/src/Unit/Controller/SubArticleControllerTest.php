<?php

namespace Drupal\Tests\ghi_content\Unit\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Controller\SubArticleController;
use Drupal\ghi_content\Entity\Article;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the sub-article AJAX controller.
 *
 * @group ghi_content
 */
class SubArticleControllerTest extends UnitTestCase {

  /**
   * Tests that directly protected sub-articles cannot be loaded by AJAX.
   */
  public function testProtectedSubArticleAccessDenied() {
    $controller = new SubArticleController();
    $account = $this->createMock(AccountInterface::class);
    $article = $this->createArticle(TRUE, FALSE);

    $access = $controller->access($article, $account);

    $this->assertFalse($access->isAllowed());
    $this->assertSame(0, $access->getCacheMaxAge());
  }

  /**
   * Tests that a protected parent context also denies deferred content.
   */
  public function testProtectedContextAccessDenied() {
    $controller = new SubArticleController();
    $this->setProtectedProperty($controller, 'requestStack', $this->createRequestStack(99));
    $this->setProtectedProperty($controller, 'entityTypeManager', $this->createEntityTypeManager($this->createArticle(TRUE, FALSE)));

    $account = $this->createMock(AccountInterface::class);
    $article = $this->createArticle(FALSE, TRUE);

    $access = $controller->access($article, $account);

    $this->assertFalse($access->isAllowed());
    $this->assertSame(0, $access->getCacheMaxAge());
  }

  /**
   * Tests that an accessible protected parent context allows deferred content.
   */
  public function testProtectedContextAccessAllowed() {
    $controller = new SubArticleController();
    $this->setProtectedProperty($controller, 'requestStack', $this->createRequestStack(99));
    $this->setProtectedProperty($controller, 'entityTypeManager', $this->createEntityTypeManager($this->createArticle(TRUE, TRUE)));

    $account = $this->createMock(AccountInterface::class);
    $article = $this->createArticle(FALSE, TRUE);

    $access = $controller->access($article, $account);

    $this->assertTrue($access->isAllowed());
    $this->assertSame(0, $access->getCacheMaxAge());
  }

  /**
   * Create a mocked article.
   *
   * @param bool $protected
   *   Whether the article should report as protected.
   * @param bool $protected_access
   *   Whether protected access should be granted.
   *
   * @return \Drupal\ghi_content\Entity\Article|\PHPUnit\Framework\MockObject\MockObject
   *   A mocked article.
   */
  private function createArticle($protected, $protected_access) {
    $article = $this->getMockBuilder(Article::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'access',
        'isProtected',
        'protectedAccess',
        'getCacheContexts',
        'getCacheTags',
        'getCacheMaxAge',
      ])
      ->getMock();
    $article->method('access')->willReturn(AccessResult::allowed());
    $article->method('isProtected')->willReturn($protected);
    $article->method('protectedAccess')->willReturn($protected_access);
    $article->method('getCacheContexts')->willReturn([]);
    $article->method('getCacheTags')->willReturn(['node:1']);
    $article->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $article;
  }

  /**
   * Create an entity type manager that loads the given context node.
   *
   * @param \Drupal\ghi_content\Entity\Article $context_node
   *   The context node to return from storage.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   A mocked entity type manager.
   */
  private function createEntityTypeManager(Article $context_node) {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(99)->willReturn($context_node);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('node')->willReturn($storage);
    return $entity_type_manager;
  }

  /**
   * Create a request stack with a context node query argument.
   *
   * @param int $context_node_id
   *   The context node id.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   A request stack.
   */
  private function createRequestStack($context_node_id) {
    $request_stack = new RequestStack();
    $request_stack->push(new Request([
      'context_node' => $context_node_id,
    ]));
    return $request_stack;
  }

  /**
   * Set a protected property on an object.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  private function setProtectedProperty($object, $property, $value) {
    $reflection_property = new \ReflectionProperty($object, $property);
    $reflection_property->setAccessible(TRUE);
    $reflection_property->setValue($object, $value);
  }

}
