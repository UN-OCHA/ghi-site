<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanWebcontentFile;
use Drupal\hpc_api\ApiObjects\FileAsset;
use Drupal\hpc_api\Plugin\FabricQuery\FileAssetQuery;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Prophecy\Argument;

/**
 * Tests the plan webcontent file block plugin.
 *
 * @group ghi_blocks
 */
class PlanWebcontentFileTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanWebcontentFile::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_webcontent_file', $definition['id']);
    $this->assertEquals('Web Content File', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertFalse($metadata->usesTitle);

    $data_sources = $metadata->dataSources;
    $this->assertArrayHasKey('file_asset', $data_sources);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('file_asset_id', $default_config);
    $this->assertNull($default_config['file_asset_id']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
    $plugin->setConfiguration([]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin->setConfiguration(['hpc' => ['file_asset_id' => NULL]]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin->setConfiguration(['hpc' => ['file_asset_id' => 1]]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $file_asset = $this->mockFileAsset(1, 'Image 1', '/url', 'credits');
    $file_asset_query = $this->prophesize(FileAssetQuery::class);
    $file_asset_query->getFileAsset(1)->willReturn($file_asset);
    $plugin->setQueryHandler('file_asset', $file_asset_query->reveal());
    $plugin->setConfiguration(['hpc' => ['file_asset_id' => 1]]);
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals('ghi_image', $build['#theme']);
    $this->assertEquals('/url', $build['#url']);
    $this->assertEquals('credits', $build['#credit']);
    $this->assertEquals('wide', $build['#style']);

    $file_asset = $this->mockFileAsset(1, 'Image 1', '/url');
    $file_asset_query = $this->prophesize(FileAssetQuery::class);
    $file_asset_query->getFileAsset(1)->willReturn($file_asset);
    $plugin->setQueryHandler('file_asset', $file_asset_query->reveal());

    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals('ghi_image', $build['#theme']);
    $this->assertEquals('/url', $build['#url']);
    $this->assertNull($build['#credit']);
    $this->assertEquals('wide', $build['#style']);
  }

  /**
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('plan', $definition['context_definitions']);
    $this->assertArrayHasKey('plan_cluster', $definition['context_definitions']);
  }

  /**
   * Tests the config form.
   */
  public function testConfigForm() {
    $plugin = $this->getBlockPlugin();
    $form = [];
    $form_state = new FormState();

    $file_asset_query = $this->prophesize(FileAssetQuery::class);
    $file_asset_query->getFileAssetsByObject('plan', Argument::any())->willReturn([]);
    $plugin->setQueryHandler('file_asset', $file_asset_query->reveal());
    $plugin->setConfiguration(['hpc' => ['file_asset_id' => 1]]);

    $config_form = $plugin->getConfigForm($form, $form_state);
    $this->assertIsArray($config_form);
    $this->assertArrayHasKey('file_asset_id', $config_form);
    $this->assertIsArray($config_form['file_asset_id']['#options']);
    $this->assertEmpty($config_form['file_asset_id']['#options']);

    $file_asset = $this->mockFileAsset(1, 'Image 1', '/url/1', $this->randomString());
    $file_asset_query = $this->prophesize(FileAssetQuery::class);
    $file_asset_query->getFileAssetsByObject('plan', Argument::any())->willReturn([$file_asset]);
    $plugin->setQueryHandler('file_asset', $file_asset_query->reveal());
    $plugin->setConfiguration(['hpc' => ['file_asset_id' => 1]]);

    $config_form = $plugin->getConfigForm($form, $form_state);
    $this->assertIsArray($config_form);
    $this->assertArrayHasKey('file_asset_id', $config_form);
    $this->assertIsArray($config_form['file_asset_id']['#options']);
    $this->assertCount(1, $config_form['file_asset_id']['#options']);
  }

  /**
   * Tests the shouldDisplayTitle method returns FALSE.
   */
  public function testShouldDisplayTitle() {
    $plugin = $this->getBlockPlugin();
    $this->assertFalse($plugin->shouldDisplayTitle());
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @param array $additional_config
   *   Additional configuration to merge with defaults.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanWebcontentFile
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $additional_config = []) {
    $configuration = array_merge([
      'file_asset_id' => NULL,
    ], $additional_config);

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_webcontent_file', $configuration, $contexts);
  }

  /**
   * Mock a file asset object.
   *
   * @return \Drupal\hpc_api\ApiObjects\FileAsset
   *   A file asset object.
   */
  private function mockFileAsset(int $id, string $name, string $uri, ?string $credits = NULL): FileAsset {
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn($uri);
    $url->setOptions(Argument::cetera())->willReturn(NULL);
    $file_asset = $this->prophesize(FileAsset::class);
    $file_asset->id()->willReturn($id);
    $file_asset->getName()->willReturn($name);
    $file_asset->getUrl()->willReturn($url->reveal());
    $file_asset->getCredit()->willReturn($credits);
    return $file_asset->reveal();
  }

}
