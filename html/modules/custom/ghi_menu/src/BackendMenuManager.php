<?php

namespace Drupal\ghi_menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Manages database-backed links in the administrative menu.
 *
 * Backend menu links are content entities, so configuration imports do not
 * update them when the corresponding Views displays change. Deploy hooks can
 * use this service to apply those changes idempotently on each environment.
 */
class BackendMenuManager {

  /**
   * The administrative menu machine name.
   */
  private const ADMIN_MENU = 'admin';

  /**
   * Public constructor.
   */
  public function __construct(private EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Synchronizes backend menu links with page displays in the Content view.
   *
   * @param string[] $excluded_display_ids
   *   View display IDs that must not have a database-backed menu link.
   *
   * @return array
   *   Counts keyed by "saved" and "deleted".
   */
  public function syncContentViewLinks(array $excluded_display_ids = ['page_all']): array {
    $view = $this->entityTypeManager->getStorage('view')->load('content');
    if (!$view instanceof ViewEntityInterface) {
      return ['saved' => 0, 'deleted' => 0];
    }

    $displays = $view->get('display');
    uasort($displays, function (array $display_a, array $display_b): int {
      return ($display_a['position'] ?? 0) <=> ($display_b['position'] ?? 0);
    });

    $saved = 0;
    $deleted = 0;
    foreach (array_values($displays) as $weight => $display) {
      if (($display['display_plugin'] ?? NULL) !== 'page') {
        continue;
      }
      $options = $display['display_options'] ?? [];
      $menu = $options['menu'] ?? [];
      if (empty($options['path']) || empty($menu['parent'])) {
        continue;
      }

      $uri = 'internal:/' . ltrim($options['path'], '/');
      if (in_array($display['id'], $excluded_display_ids, TRUE) || ($menu['type'] ?? NULL) === 'none' || ($options['enabled'] ?? NULL) === FALSE) {
        $deleted += $this->deleteLinks($uri, $menu['parent']);
        continue;
      }
      if (empty($menu['title'])) {
        continue;
      }

      $this->createOrUpdateLink($menu['title'], $uri, $menu['parent'], $weight - 10);
      $saved++;
    }

    return ['saved' => $saved, 'deleted' => $deleted];
  }

  /**
   * Creates or updates one administrative menu link.
   *
   * Duplicate matching links are removed to keep subsequent runs idempotent.
   */
  public function createOrUpdateLink(string $title, string $uri, string $parent, int $weight): MenuLinkContentInterface {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $properties = $this->getLinkProperties($uri, $parent);
    $links = array_values($storage->loadByProperties($properties));
    $values = $properties + [
      'title' => $title,
      'weight' => $weight,
      'enabled' => TRUE,
    ];

    if (empty($links)) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      $link = $storage->create($values);
    }
    else {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      $link = array_shift($links);
      foreach ($values as $field_name => $value) {
        $link->set($field_name, $value);
      }
      foreach ($links as $duplicate) {
        $duplicate->delete();
      }
    }

    $link->save();
    return $link;
  }

  /**
   * Deletes administrative menu links matching a URI and optional parent.
   *
   * @return int
   *   The number of deleted links.
   */
  public function deleteLinks(string $uri, ?string $parent = NULL): int {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $links = $storage->loadByProperties($this->getLinkProperties($uri, $parent));
    if (empty($links)) {
      return 0;
    }
    $storage->delete($links);
    return count($links);
  }

  /**
   * Builds entity properties that identify an administrative menu link.
   */
  private function getLinkProperties(string $uri, ?string $parent): array {
    $properties = [
      'link' => ['uri' => $uri],
      'menu_name' => self::ADMIN_MENU,
    ];
    if ($parent !== NULL) {
      $properties['parent'] = $parent;
    }
    return $properties;
  }

}
