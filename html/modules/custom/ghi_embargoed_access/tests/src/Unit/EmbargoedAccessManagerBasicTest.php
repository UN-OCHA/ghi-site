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
   * Tests embargoedAccessEnabled when disabled.
   *
   * @covers ::embargoedAccessEnabled
   */
  public function testEmbargoedAccessEnabledDisabled(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(FALSE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $this->assertFalse($this->embargoedAccessManager->embargoedAccessEnabled());
  }

  /**
   * Tests embargoedAccessEnabled when enabled.
   *
   * @covers ::embargoedAccessEnabled
   */
  public function testEmbargoedAccessEnabledEnabled(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

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

    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(FALSE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $this->assertTrue($this->embargoedAccessManager->entityAccess($node));
  }

  /**
   * Tests isProtected when node is protected.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedTrue(): void {
    $fieldItem = (object) ['is_protected' => TRUE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $this->assertTrue($this->embargoedAccessManager->isProtected($node));
  }

  /**
   * Tests isProtected when node is not protected.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedFalse(): void {
    $fieldItem = (object) ['is_protected' => FALSE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $this->assertFalse($this->embargoedAccessManager->isProtected($node));
  }

  /**
   * Tests isProtected when embargo is disabled.
   *
   * @covers ::isProtected
   */
  public function testIsProtectedEmbargoDisabled(): void {
    $node = $this->createMock(NodeInterface::class);

    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('enabled')
      ->willReturn(FALSE);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

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
    $fieldItem = (object) ['is_protected' => TRUE];

    $parentNode = $this->createMock(NodeInterface::class);
    $parentNode->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $parentNode->expects($this->any())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $subpageNode = $this->createMock(SubpageNodeInterface::class);
    $subpageNode->expects($this->once())
      ->method('getParentBaseNode')
      ->willReturn($parentNode);

    // Mock embargoedAccessEnabled to return TRUE for the isProtected call.
    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $result = $this->embargoedAccessManager->getProtectedParent($subpageNode);
    $this->assertSame($parentNode, $result);
  }

  /**
   * Tests protectNode when already protected.
   *
   * @covers ::protectNode
   */
  public function testProtectNodeAlreadyProtected(): void {
    $fieldItem = (object) ['is_protected' => TRUE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);

    // Only one call to get() since method returns early when already protected.
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    // Node should not be saved if already protected.
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
    $fieldItem = (object) ['is_protected' => FALSE];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);

    // First call for isProtected check, second call for setValue.
    $node->expects($this->exactly(2))
      ->method('get')
      ->with('field_protected')
      ->willReturnOnConsecutiveCalls($fieldItem, $fieldItemList);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    // Expectations for protecting the node.
    $fieldItemList->expects($this->once())
      ->method('setValue')
      ->with([
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
      ]);

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

    $this->embargoedAccessManager->protectNode($node);
  }

  /**
   * Tests unprotectNode when already unprotected.
   *
   * @covers ::unprotectNode
   */
  public function testUnprotectNodeAlreadyUnprotected(): void {
    $fieldItem = (object) ['is_protected' => FALSE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    // Node should not be saved if already unprotected.
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
    $fieldItem = (object) ['is_protected' => TRUE];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);

    // First call for isProtected check, second call for setValue.
    $node->expects($this->exactly(2))
      ->method('get')
      ->with('field_protected')
      ->willReturnOnConsecutiveCalls($fieldItem, $fieldItemList);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    // Expectations for unprotecting the node.
    $fieldItemList->expects($this->once())
      ->method('setValue')
      ->with(NULL);

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

    $this->embargoedAccessManager->unprotectNode($node);
  }

  /**
   * Tests entityAccess with password protection when access denied.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessWithPasswordProtection(): void {
    $fieldItem = (object) ['is_protected' => TRUE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

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
    $fieldItem = (object) ['is_protected' => TRUE];

    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->any())
      ->method('hasField')
      ->with('field_protected')
      ->willReturn(TRUE);
    $node->expects($this->once())
      ->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);

    $this->passwordAccessManager->expects($this->once())
      ->method('hasUserAccessToEntity')
      ->with($node)
      ->willReturn(TRUE);

    $this->assertTrue($this->embargoedAccessManager->entityAccess($node));
  }

}
