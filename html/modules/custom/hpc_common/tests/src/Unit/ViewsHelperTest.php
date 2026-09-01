<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ViewsHelper;

/**
 * @covers Drupal\hpc_common\Helpers\ViewsHelper
 */
class ViewsHelperTest extends UnitTestCase {

  /**
   * Test defaultFieldOptions returns expected structure.
   *
   * @group ViewsHelper
   */
  public function testDefaultFieldOptionsStructure() {
    $options = ViewsHelper::defaultFieldOptions();

    $this->assertArrayHasKey('exclude', $options);
    $this->assertArrayHasKey('alter', $options);
    $this->assertArrayHasKey('element_type', $options);
    $this->assertArrayHasKey('element_class', $options);
    $this->assertArrayHasKey('element_label_type', $options);
    $this->assertArrayHasKey('element_label_class', $options);
    $this->assertArrayHasKey('element_label_colon', $options);
    $this->assertArrayHasKey('element_wrapper_type', $options);
    $this->assertArrayHasKey('element_wrapper_class', $options);
    $this->assertArrayHasKey('element_default_classes', $options);
    $this->assertArrayHasKey('empty', $options);
    $this->assertArrayHasKey('hide_empty', $options);
    $this->assertArrayHasKey('empty_zero', $options);
    $this->assertArrayHasKey('hide_alter_empty', $options);
  }

  /**
   * Test defaultFieldOptions default values.
   *
   * @group ViewsHelper
   */
  public function testDefaultFieldOptionsDefaultValues() {
    $options = ViewsHelper::defaultFieldOptions();

    $this->assertFalse($options['exclude']);
    $this->assertFalse($options['alter']['alter_text']);
    $this->assertSame('', $options['alter']['text']);
    $this->assertFalse($options['alter']['make_link']);
    $this->assertSame('', $options['alter']['path']);
    $this->assertSame('', $options['element_type']);
    $this->assertSame('', $options['element_class']);
    $this->assertTrue($options['element_label_colon']);
    $this->assertTrue($options['element_default_classes']);
    $this->assertFalse($options['hide_empty']);
    $this->assertFalse($options['empty_zero']);
    $this->assertTrue($options['hide_alter_empty']);
  }

  /**
   * Test defaultFieldOptions alter key structure.
   *
   * @group ViewsHelper
   */
  public function testDefaultFieldOptionsAlterKeys() {
    $options = ViewsHelper::defaultFieldOptions();
    $alter = $options['alter'];

    $expected_keys = [
      'alter_text', 'text', 'make_link', 'path', 'absolute', 'external',
      'replace_spaces', 'path_case', 'trim_whitespace', 'alt', 'rel',
      'link_class', 'prefix', 'suffix', 'target', 'nl2br', 'max_length',
      'word_boundary', 'ellipsis', 'more_link', 'more_link_text',
      'more_link_path', 'strip_tags', 'trim', 'preserve_tags', 'html',
    ];

    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $alter, "Missing alter key: $key");
    }
  }

  /**
   * Test defaultFieldOptions returns array.
   *
   * @group ViewsHelper
   */
  public function testDefaultFieldOptionsReturnsArray() {
    $options = ViewsHelper::defaultFieldOptions();
    $this->assertIsArray($options);
    $this->assertIsArray($options['alter']);
  }

}
