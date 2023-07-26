<?php

/**
 * @file
 * Post update functions for GHI Menu.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Check if an update has already been run.
 *
 * @param string $name
 *   The name of the update.
 *
 * @return bool
 *   TRUE if it has already run, FALSE otherwise.
 */
function ghi_menu_update_already_run($name) {
  return in_array('ghi_menu_post_update_' . $name, \Drupal::keyValue('post_update')->get('existing_updates'));
}

/**
 * Add custom subpages backend page to admin menu.
 */
function ghi_menu_deploy_adjust_admin_menu(&$sandbox) {
  if (ghi_menu_update_already_run('adjust_admin_menu')) {
    return;
  }
  /** @var \Drupal\Core\Menu\MenuLinkManager $menu_link_manager */
  $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
  $node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple(NULL);
  $links = $menu_link_manager->loadLinksByRoute('node.add_page', [], 'admin');
  foreach ($node_types as $node_type) {
    $links = $links + $menu_link_manager->loadLinksByRoute('node.add', ['node_type' => $node_type->id()], 'admin');
  }
  foreach ($links as $menu_link) {
    $menu_link->updateLink([
      'enabled' => FALSE,
    ], TRUE);
  }

  $section_link = reset(\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties([
    'title' => 'Sections',
    'link' => [
      'uri' => 'internal:/admin/content/sections',
    ],
    'menu_name' => 'admin',
  ]));
  if ($section_link) {
    $section_link->delete();
  }

  /** @var \Drupal\views\Entity\View $view */
  $view = \Drupal::entityTypeManager()
    ->getStorage('view')
    ->load('content');
  $displays = $view->get('display');
  uasort($displays, function ($display_a, $display_b) {
    return $display_a['position'] - $display_b['position'];
  });

  $exclude_displays = [
    'page_all',
    'page_overview',
  ];

  foreach (array_values($displays) as $weight => $display) {
    if ($display['display_plugin'] != 'page') {
      continue;
    }
    $options = $display['display_options'];
    if (in_array($display['id'], $exclude_displays)) {
      $existing_link = reset(\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties([
        'link' => [
          'uri' => 'internal:/' . ltrim($options['path'], '/'),
        ],
        'menu_name' => 'admin',
        'parent' => $options['menu']['parent'],
      ]));
      if (!empty($existing_link)) {
        $existing_link->delete();
      }
      continue;
    }
    if (($options['enabled'] ?? NULL) === FALSE) {
      continue;
    }
    $existing_links = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties([
      'link' => [
        'uri' => 'internal:/' . ltrim($options['path'], '/'),
      ],
      'menu_name' => 'admin',
      'parent' => $options['menu']['parent'],
    ]);
    $menu_link = NULL;
    if (count($existing_links) == 1) {
      $menu_link = reset($existing_links);
    }
    else {
      $menu_link = MenuLinkContent::create([
        'link' => [
          'uri' => 'internal:/' . ltrim($options['path'], '/'),
        ],
        'menu_name' => 'admin',
      ]);
    }
    $menu_link->set('title', $options['menu']['title']);
    $menu_link->set('weight', $weight - 10);
    $menu_link->set('parent', $options['menu']['parent']);
    $menu_link->save();
  }
}
