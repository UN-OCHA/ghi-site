<?php

namespace Drupal\ghi_form_elements\Traits;

use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Helper trait for classes using a configuration container.
 */
trait ConfigurationContainerGroup {

  /**
   * Get the items that represent groups.
   *
   * @param array $items
   *   An array of items.
   *
   * @return array
   *   An array of the items that represent groups.
   */
  public static function getGroups(array $items) {
    $items = array_filter($items, function ($item) {
      return !empty($item['item_type']);
    });

    // First assemble the groups.
    $groups = array_filter($items, function ($item) {
      return $item['item_type'] == 'item_group';
    });
    ArrayHelper::sortArrayByNumericKey($groups, 'weight', EndpointQuery::SORT_ASC);
    return $groups;
  }

  /**
   * Get an item by its id.
   *
   * @param array $items
   *   An array of items.
   * @param int $id
   *   The id to look for.
   *
   * @return array|null
   *   A single item array or NULL if not found.
   */
  public static function getItemById(array $items, $id) {
    if ($id === NULL) {
      return NULL;
    }
    $index = self::getItemIndexById($items, $id);
    return $index !== NULL && array_key_exists($index, $items) ? $items[$index] : NULL;
  }

  /**
   * Get the index of an item by its id.
   *
   * @param array $items
   *   An array of items.
   * @param int $id
   *   The id to look for.
   *
   * @return int
   *   The index of the item in the given items array.
   */
  public static function getItemIndexById(array $items, $id) {
    if ($id === NULL) {
      return NULL;
    }
    $keys = array_keys(array_filter($items, function ($item) use ($id) {
      return (int) $item['id'] === (int) $id;
    }));
    return $keys && count($keys) ? $keys[0] : NULL;
  }

  /**
   * Build a tree structure from a flat list of items.
   *
   * @param array $items
   *   The flat list of items. Each one must have an id and optionally a pid to
   *   indicate under which parent the item should appear in the tree.
   *
   * @return array
   *   An array representing the items in an hierarchical tree.
   */
  public static function buildTree(array $items) {
    $items = array_filter($items, function ($item) {
      return !empty($item['item_type']);
    });

    // First assemble the groups.
    $groups = self::getGroups($items);
    if (empty($groups)) {
      ArrayHelper::sortArrayByNumericKey($items, 'weight', EndpointQuery::SORT_ASC);
      return $items;
    }

    $children = array_filter($items, function ($item) {
      return $item['item_type'] != 'item_group';
    });

    foreach ($children as $child_key => $item) {
      $parent = $item['pid'];
      $group_index = self::getItemIndexById($groups, $parent);
      if ($group_index === NULL) {
        continue;
      }
      $group = $groups[$group_index] ?? NULL;
      if (!$group) {
        continue;
      }
      if (!array_key_exists('children', $group)) {
        $group['children'] = [];
      }
      unset($item['pid']);
      $group['children'][] = $item;
      unset($children[$child_key]);

      $groups[$group_index] = $group;
    }

    foreach ($groups as &$group) {
      if (empty($group['children'])) {
        continue;
      }
      ArrayHelper::sortArrayByNumericKey($group['children'], 'weight', EndpointQuery::SORT_ASC);
    }
    ArrayHelper::sortArrayByNumericKey($children, 'weight', EndpointQuery::SORT_ASC);
    ArrayHelper::sortArrayByNumericKey($groups, 'weight', EndpointQuery::SORT_ASC);
    return array_merge($groups, $children);
  }

  /**
   * Build a flat list from a tree structure.
   *
   * @param array $tree
   *   The items tree. See self::buildTree() for details.
   * @param int $pid
   *   The parent id, used during recursion..
   *
   * @return array
   *   An array with a flat list of items.
   */
  public static function buildFlatList(array $tree, $pid = NULL) {
    $sorted = [];
    foreach (array_values($tree) as $item) {
      if (!array_key_exists('item_type', $item)) {
        continue;
      }
      $item['pid'] = $pid;
      $sorted[] = $item;
      if (!empty($item['children'])) {
        $child_list = self::buildFlatList($item['children'], $item['id']);
        if (empty($child_list)) {
          continue;
        }
        ArrayHelper::sortArrayByNumericKey($child_list, 'weight', EndpointQuery::SORT_ASC);
        foreach (array_values($child_list) as $child) {
          $child['pid'] = $item['id'];
          $sorted[] = $child;
        }
      }
    }
    return array_map(function ($item) {
      unset($item['children']);
      return $item;
    }, $sorted);
  }

}
