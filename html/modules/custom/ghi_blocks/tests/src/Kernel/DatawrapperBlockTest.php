<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Plugin\Block\Generic\Datawrapper;

/**
 * Tests the datawrapper block plugin.
 *
 * @group ghi_blocks
 */
class DatawrapperBlockTest extends BlockKernelTestBase {

  const EMBED_CODE_VALID = '<iframe src="https://datawrapper.dwcdn.net/CHART_ID"></iframe>';
  const EMBED_CODE_INVALID = '<iframe src="https://invalid.url/CHART_ID"></iframe>';

  /**
   * Tests the block.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(Datawrapper::class, $plugin);

    $allowed_hosts = $this->callPrivateMethod($plugin, 'getAllowedHosts');
    $this->assertCount(1, $allowed_hosts);
    $this->assertArrayHasKey('datawrapper.dwcdn.net', $allowed_hosts);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin = $this->getBlockPlugin(self::EMBED_CODE_VALID);
    $build = $plugin->buildContent();
    $this->assertArrayHasKey(0, $build);
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->getConfigForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('embed', $form);

    // Prepare form validation.
    $form['embed']['#parents'] = ['container'];
    $form_state->set('current_subform', 'basic');

    // Validate a valid embed code.
    $form_state->setValue(['basic', 'embed'], self::EMBED_CODE_VALID);
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertEmpty($form_state->getErrors());

    // Validate an invalid embed code.
    $form_state->setValue(['basic', 'embed'], self::EMBED_CODE_INVALID);
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Get a block plugin.
   *
   * @param array $embed
   *   The embed code for the plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\Datawrapper
   *   The block plugin.
   */
  private function getBlockPlugin($embed = '') {
    $configuration = [
      'embed' => $embed,
    ];
    return $this->createBlockPlugin('generic_datawrapper', $configuration);
  }

}
