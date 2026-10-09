<?php

namespace Drupal\Tests\ghi_subpages\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Tests administration-menu configuration shared by the site's node types.
 *
 * @group ghi_subpages
 */
class AdminToolbarConfigTest extends UnitTestCase {

  /**
   * Tests that no enabled add link remains below its disabled parent.
   */
  public function testGenericNodeAddLinksAreDisabled(): void {
    $config_directory = dirname(DRUPAL_ROOT) . '/config';
    $menu_config_path = $config_directory . '/core.menu.static_menu_link_overrides.yml';
    $this->assertFileExists($menu_config_path);
    $menu_config = Yaml::decode(file_get_contents($menu_config_path));
    $definitions = $menu_config['definitions'] ?? [];
    $parent_plugin_id = 'admin_toolbar_tools.extra_links:node.add';
    $parent_override_id = str_replace('.', '__', $parent_plugin_id);

    $this->assertArrayHasKey($parent_override_id, $definitions);
    $this->assertFalse($definitions[$parent_override_id]['enabled']);

    $node_type_config_paths = glob($config_directory . '/node.type.*.yml');
    $this->assertNotEmpty($node_type_config_paths);
    foreach ($node_type_config_paths as $node_type_config_path) {
      $node_type_config = Yaml::decode(file_get_contents($node_type_config_path));
      $bundle = $node_type_config['type'] ?? NULL;
      $this->assertNotEmpty($bundle, "Missing node type ID in $node_type_config_path.");
      $plugin_id = $parent_plugin_id . '.' . $bundle;
      $override_id = str_replace('.', '__', $plugin_id);
      // A missing level corrupts Drupal's depth-based menu reconstruction, so
      // every generated child must be disabled together with its parent.
      $message = "The generic $bundle creation link must stay disabled while its parent is disabled.";
      $this->assertArrayHasKey($override_id, $definitions, $message);
      $this->assertSame($parent_plugin_id, $definitions[$override_id]['parent'], $message);
      $this->assertFalse($definitions[$override_id]['enabled'], $message);
    }
  }

}
