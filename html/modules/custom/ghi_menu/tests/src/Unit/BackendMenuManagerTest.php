<?php

namespace Drupal\Tests\ghi_menu\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_menu\BackendMenuManager;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewEntityInterface;

/**
 * Tests the backend menu manager.
 *
 * @group ghi_menu
 */
class BackendMenuManagerTest extends UnitTestCase {

  /**
   * Tests creating a missing backend menu link.
   */
  public function testCreateOrUpdateLinkCreatesLink(): void {
    $properties = [
      'link' => ['uri' => 'internal:/admin/content/articles'],
      'menu_name' => 'admin',
      'parent' => 'system.admin_content',
    ];
    $values = $properties + [
      'title' => 'Articles',
      'weight' => -5,
      'enabled' => TRUE,
    ];

    $link = $this->createMock(MenuLinkContentInterface::class);
    $link->expects($this->once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadByProperties')->with($properties)->willReturn([]);
    $storage->expects($this->once())->method('create')->with($values)->willReturn($link);

    $manager = new BackendMenuManager($this->mockEntityTypeManagerWithStorage('menu_link_content', $storage));
    $this->assertSame($link, $manager->createOrUpdateLink('Articles', 'internal:/admin/content/articles', 'system.admin_content', -5));
  }

  /**
   * Tests updating an existing link and removing duplicates.
   */
  public function testCreateOrUpdateLinkUpdatesLinkAndRemovesDuplicates(): void {
    $link = $this->createMock(MenuLinkContentInterface::class);
    $link->expects($this->exactly(6))->method('set')->willReturnSelf();
    $link->expects($this->once())->method('save');
    $duplicate = $this->createMock(MenuLinkContentInterface::class);
    $duplicate->expects($this->once())->method('delete');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$link, $duplicate]);
    $storage->expects($this->never())->method('create');

    $manager = new BackendMenuManager($this->mockEntityTypeManagerWithStorage('menu_link_content', $storage));
    $this->assertSame($link, $manager->createOrUpdateLink('Articles', 'internal:/admin/content/articles', 'system.admin_content', -5));
  }

  /**
   * Tests deleting matching backend menu links.
   */
  public function testDeleteLinks(): void {
    $links = [
      $this->createMock(MenuLinkContentInterface::class),
      $this->createMock(MenuLinkContentInterface::class),
    ];
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn($links);
    $storage->expects($this->once())->method('delete')->with($links);

    $manager = new BackendMenuManager($this->mockEntityTypeManagerWithStorage('menu_link_content', $storage));
    $this->assertSame(2, $manager->deleteLinks('internal:/admin/content/articles', 'system.admin_content'));
  }

  /**
   * Tests synchronization with Content view page displays.
   */
  public function testSyncContentViewLinks(): void {
    $view = $this->createMock(ViewEntityInterface::class);
    $view->method('get')->with('display')->willReturn([
      'default' => [
        'id' => 'default',
        'display_plugin' => 'default',
        'position' => 0,
      ],
      'page_articles' => [
        'id' => 'page_articles',
        'display_plugin' => 'page',
        'position' => 3,
        'display_options' => [
          'path' => 'admin/content/articles',
          'menu' => [
            'type' => 'tab',
            'title' => 'Articles',
            'parent' => 'system.admin_content',
          ],
        ],
      ],
      'page_all' => [
        'id' => 'page_all',
        'display_plugin' => 'page',
        'position' => 8,
        'display_options' => [
          'path' => 'admin/content/all',
          'menu' => [
            'type' => 'none',
            'title' => 'All',
            'parent' => 'system.admin_content',
          ],
        ],
      ],
    ]);
    $view_storage = $this->createMock(EntityStorageInterface::class);
    $view_storage->method('load')->with('content')->willReturn($view);
    $entity_type_manager = $this->mockEntityTypeManagerWithStorage('view', $view_storage);

    $manager = $this->getMockBuilder(BackendMenuManager::class)
      ->setConstructorArgs([$entity_type_manager])
      ->onlyMethods(['createOrUpdateLink', 'deleteLinks'])
      ->getMock();
    $manager->expects($this->once())
      ->method('createOrUpdateLink')
      ->with('Articles', 'internal:/admin/content/articles', 'system.admin_content', -9);
    $manager->expects($this->once())
      ->method('deleteLinks')
      ->with('internal:/admin/content/all', 'system.admin_content')
      ->willReturn(1);

    $this->assertSame(['saved' => 1, 'deleted' => 1], $manager->syncContentViewLinks());
  }

  /**
   * Mocks an entity type manager with one configured storage.
   */
  private function mockEntityTypeManagerWithStorage(string $entity_type_id, EntityStorageInterface $storage): EntityTypeManagerInterface {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with($entity_type_id)->willReturn($storage);
    return $entity_type_manager;
  }

}
