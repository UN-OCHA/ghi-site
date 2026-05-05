<?php

namespace Drupal\Tests\ghi_embargoed_access\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\Container;
use Drupal\entity_access_password\Service\PasswordAccessManagerInterface;
use Drupal\entity_access_password\Service\RouteParserInterface;
use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Basic unit tests for the EmbargoedAccessManager.
 *
 * @coversDefaultClass \Drupal\ghi_embargoed_access\EmbargoedAccessManager
 * @group ghi_embargoed_access
 */
class EmbargoedAccessManagerBasicTest extends UnitTestCase {

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The embargoed access manager under test.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * The mocked password access manager.
   *
   * @var \Drupal\entity_access_password\Service\PasswordAccessManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $passwordAccessManager;

  /**
   * The mocked search API tracking manager.
   *
   * @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $searchApiTrackingManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up a mock container to handle static calls like
    // Cache::invalidateTags().
    $container = new Container();
    $cache_tags_invalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');
    $container->set('cache_tags.invalidator', $cache_tags_invalidator);
    \Drupal::setContainer($container);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->searchApiTrackingManager = $this->createMock(ContentEntityTrackingManager::class);
    $csrfToken = $this->createMock(CsrfTokenGenerator::class);
    $redirectDestination = $this->createMock(RedirectDestinationInterface::class);
    $routeParser = $this->createMock(RouteParserInterface::class);
    $this->passwordAccessManager = $this->createMock(PasswordAccessManagerInterface::class);

    $this->embargoedAccessManager = new EmbargoedAccessManager(
      $entityTypeManager,
      $entityFieldManager,
      $this->configFactory,
      $this->searchApiTrackingManager,
      $csrfToken,
      $redirectDestination,
      $routeParser,
      $this->passwordAccessManager
    );
  }

  /**
   * Creates a protected field mock with the given protection state.
   *
   * @param bool $is_protected
   *   Whether the protected field should be enabled.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked protected field.
   */
  protected function createProtectionField(bool $is_protected) {
    $field = $this->createMock(FieldItemListInterface::class);
    // The production code uses empty(), which checks __isset() before __get().
    $field->expects($this->once())
      ->method('__isset')
      ->with('is_protected')
      ->willReturn($is_protected);
    if ($is_protected) {
      $field->expects($this->once())
        ->method('__get')
        ->with('is_protected')
        ->willReturn(TRUE);
    }
    return $field;
  }

  /**
   * Creates a node mock with a protected field state.
   *
   * @param bool $is_protected
   *   Whether the protected field should be enabled.
   * @param object|null $field_item
   *   The field item to return, or NULL to create a simple field item object.
   * @param int|null $expected_get_calls
   *   The expected number of protected field reads, or NULL for any number.
   *
   * @return \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked node.
   */
  protected function createNodeWithProtectionState(bool $is_protected, ?object $field_item = NULL, ?int $expected_get_calls = NULL) {
    $field_item = $field_item ?? (object) ['is_protected' => $is_protected];
    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $get_expectation = $expected_get_calls === NULL ? $this->any() : $this->exactly($expected_get_calls);
    $node->expects($get_expectation)
      ->method('get')
      ->with('field_protected')
      ->willReturn($field_item);
    return $node;
  }

  /**
   * Mocks the global embargo access state.
   *
   * @param bool $enabled
   *   Whether embargoed access should be enabled.
   * @param string $expected_calls
   *   The expected number of config reads: 'once' or 'any'.
   */
  protected function mockEmbargoedAccessState(bool $enabled, string $expected_calls = 'once'): void {
    $config = $this->createMock(Config::class);
    $config->expects($expected_calls == 'any' ? $this->any() : $this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn($enabled);

    $this->configFactory->expects($expected_calls == 'any' ? $this->any() : $this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);
  }

  /**
   * Expects the given node to be saved and queued for reindexing.
   *
   * @param \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject $node
   *   The mocked node.
   */
  protected function expectNodeSaveAndReindex($node): void {
    $node->expects($this->once())
      ->method('setNewRevision')
      ->with(FALSE);
    $node->expects($this->once())
      ->method('setSyncing')
      ->with(TRUE);
    $node->expects($this->once())
      ->method('save');
    $node->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['node:1']);

    $this->searchApiTrackingManager->expects($this->once())
      ->method('entityUpdate')
      ->with($node);
  }

  /**
   * Tests embargoedAccessEnabled when disabled.
   *
   * @covers ::embargoedAccessEnabled
   */
  public function testEmbargoedAccessEnabledDisabled(): void {
    $this->mockEmbargoedAccessState(FALSE);
    $this->assertFalse($this->embargoedAccessManager->embargoedAccessEnabled());
  }

  /**
   * Tests embargoedAccessEnabled when enabled.
   *
   * @covers ::embargoedAccessEnabled
   */
  public function testEmbargoedAccessEnabledEnabled(): void {
    $this->mockEmbargoedAccessState(TRUE);
    $this->assertTrue($this->embargoedAccessManager->embargoedAccessEnabled());
  }

  /**
   * Tests supportsProtections.
   *
   * @covers ::supportsProtections
   */
  public function testSupportsProtections(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);

    $this->assertTrue($this->embargoedAccessManager->supportsProtections($node));
  }

  /**
   * Tests supportsProtections when field is missing.
   *
   * @covers ::supportsProtections
   */
  public function testSupportsProtectionsNoField(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(FALSE);

    $this->assertFalse($this->embargoedAccessManager->supportsProtections($node));
  }

  /**
   * Tests entityAccess when embargo is disabled.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessEmbargoDisabled(): void {
    $node = $this->createMock(NodeInterface::class);
    $this->mockEmbargoedAccessState(FALSE);
    $this->assertTrue($this->embargoedAccessManager->entityAccess($node));
  }

  /**
   * Tests isProtected when node is protected.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedTrue(): void {
    $node = $this->createNodeWithProtectionState(TRUE);
    $this->mockEmbargoedAccessState(TRUE);
    $this->assertTrue($this->embargoedAccessManager->isProtected($node));
  }

  /**
   * Tests isProtected when node is not protected.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedFalse(): void {
    $node = $this->createNodeWithProtectionState(FALSE);
    $this->mockEmbargoedAccessState(TRUE);
    $this->assertFalse($this->embargoedAccessManager->isProtected($node));
  }

  /**
   * Tests isProtected when embargo is disabled.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedEmbargoDisabled(): void {
    $node = $this->createMock(NodeInterface::class);
    $this->mockEmbargoedAccessState(FALSE);
    $this->assertFalse($this->embargoedAccessManager->isProtected($node));
  }

  /**
   * Tests getProtectedParent returns null when no parent.
   *
   * @covers ::getProtectedParent
   */
  public function testGetProtectedParentNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $result = $this->embargoedAccessManager->getProtectedParent($node);
    $this->assertNull($result);
  }

  /**
   * Tests getProtectedParent returns parent for subpage with protected parent.
   *
   * @covers ::getProtectedParent
   */
  public function testGetProtectedParentSubpage(): void {
    $parentNode = $this->createNodeWithProtectionState(TRUE);
    $subpageNode = $this->createMock(SubpageNodeInterface::class);
    $subpageNode->expects($this->once())
      ->method('getParentBaseNode')
      ->willReturn($parentNode);
    $this->mockEmbargoedAccessState(TRUE, 'any');

    $result = $this->embargoedAccessManager->getProtectedParent($subpageNode);
    $this->assertSame($parentNode, $result);
  }

  /**
   * Tests protectNode when already protected.
   *
   * @covers ::protectNode
   */
  public function testProtectNodeAlreadyProtected(): void {
    $node = $this->createNodeWithProtectionState(TRUE);
    $this->mockEmbargoedAccessState(TRUE, 'any');
    // Already protected nodes should be left untouched.
    $node->expects($this->never())
      ->method('save');

    $this->embargoedAccessManager->protectNode($node);
  }

  /**
   * Tests protectNode successfully protects node.
   *
   * @covers ::protectNode
   */
  public function testProtectNodeSuccessful(): void {
    $fieldItemList = $this->createProtectionField(FALSE);
    $node = $this->createNodeWithProtectionState(FALSE, $fieldItemList, 2);
    $this->mockEmbargoedAccessState(TRUE, 'any');

    // An unprotected node should get a protected field value and be persisted.
    $fieldItemList->expects($this->once())
      ->method('setValue')
      ->with([
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
      ]);
    $this->expectNodeSaveAndReindex($node);

    $this->embargoedAccessManager->protectNode($node);
  }

  /**
   * Tests unprotectNode when already unprotected.
   *
   * @covers ::unprotectNode
   */
  public function testUnprotectNodeAlreadyUnprotected(): void {
    $node = $this->createNodeWithProtectionState(FALSE);
    $this->mockEmbargoedAccessState(TRUE, 'any');
    // Already unprotected nodes should be left untouched.
    $node->expects($this->never())
      ->method('save');

    $this->embargoedAccessManager->unprotectNode($node);
  }

  /**
   * Tests unprotectNode successfully unprotects node.
   *
   * @covers ::unprotectNode
   */
  public function testUnprotectNodeSuccessful(): void {
    $fieldItemList = $this->createProtectionField(TRUE);
    $node = $this->createNodeWithProtectionState(TRUE, $fieldItemList, 2);
    $this->mockEmbargoedAccessState(TRUE, 'any');

    // A protected node should have its stored field cleared and be persisted.
    $fieldItemList->expects($this->once())
      ->method('setValue')
      ->with(NULL);
    $this->expectNodeSaveAndReindex($node);

    $this->embargoedAccessManager->unprotectNode($node);
  }

  /**
   * Tests unprotectNode clears stored protection when embargo is disabled.
   *
   * @covers ::unprotectNode
   */
  public function testUnprotectNodeSuccessfulWhenEmbargoDisabled(): void {
    $fieldItemList = $this->createProtectionField(TRUE);
    $node = $this->createNodeWithProtectionState(TRUE, $fieldItemList, 2);
    $this->mockEmbargoedAccessState(FALSE, 'any');

    // Even with global protection off, the stored page flag must be cleared.
    $fieldItemList->expects($this->once())
      ->method('setValue')
      ->with(NULL);
    $this->expectNodeSaveAndReindex($node);

    $this->embargoedAccessManager->unprotectNode($node);
  }

  /**
   * Tests entityAccess with password protection when access denied.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessWithPasswordProtection(): void {
    $node = $this->createNodeWithProtectionState(TRUE);
    $this->mockEmbargoedAccessState(TRUE, 'any');
    $this->passwordAccessManager->expects($this->once())
      ->method('hasUserAccessToEntity')
      ->with($node)
      ->willReturn(FALSE);
    $this->assertFalse($this->embargoedAccessManager->entityAccess($node));
  }

  /**
   * Tests entityAccess with password protection when access granted.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessWithPasswordProtectionGranted(): void {
    $node = $this->createNodeWithProtectionState(TRUE);
    $this->mockEmbargoedAccessState(TRUE, 'any');
    $this->passwordAccessManager->expects($this->once())
      ->method('hasUserAccessToEntity')
      ->with($node)
      ->willReturn(TRUE);
    $this->assertTrue($this->embargoedAccessManager->entityAccess($node));
  }

}
