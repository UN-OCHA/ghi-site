<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanWebcontentFile;
use Drupal\hpc_api\ApiObjects\Resource;
use Drupal\hpc_api\Plugin\FabricQuery\ResourceQuery;
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
    $this->assertArrayHasKey('resource', $data_sources);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('resource_id', $default_config);
    $this->assertNull($default_config['resource_id']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
    $plugin->setConfiguration([]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin->setConfiguration(['hpc' => ['resource_id' => NULL]]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin->setConfiguration(['hpc' => ['resource_id' => 1]]);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $resource = $this->mockResource(1, 'Image 1', '/url', 'credits');
    $resource_query = $this->prophesize(ResourceQuery::class);
    $resource_query->getResource(1)->willReturn($resource);
    $plugin->setQueryHandler('resource', $resource_query->reveal());
    $plugin->setConfiguration(['hpc' => ['resource_id' => 1]]);
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals('ghi_image', $build['#theme']);
    $this->assertEquals('/url', $build['#url']);
    $this->assertEquals('credits', $build['#credit']);
    $this->assertEquals('wide', $build['#style']);

    $resource = $this->mockResource(1, 'Image 1', '/url');
    $resource_query = $this->prophesize(ResourceQuery::class);
    $resource_query->getResource(1)->willReturn($resource);
    $plugin->setQueryHandler('resource', $resource_query->reveal());

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

    $resource_query = $this->prophesize(ResourceQuery::class);
    $resource_query->getResourcesByObject('plan', Argument::any())->willReturn([]);
    $plugin->setQueryHandler('resource', $resource_query->reveal());
    $plugin->setConfiguration(['hpc' => ['resource_id' => 1]]);

    $config_form = $plugin->getConfigForm($form, $form_state);
    $this->assertIsArray($config_form);
    $this->assertArrayHasKey('resource_id', $config_form);
    $this->assertIsArray($config_form['resource_id']['#options']);
    $this->assertEmpty($config_form['resource_id']['#options']);

    $resource = $this->mockResource(1, 'Image 1', '/url/1', $this->randomString());
    $resource_query = $this->prophesize(ResourceQuery::class);
    $resource_query->getResourcesByObject('plan', Argument::any())->willReturn([$resource]);
    $plugin->setQueryHandler('resource', $resource_query->reveal());
    $plugin->setConfiguration(['hpc' => ['resource_id' => 1]]);

    $config_form = $plugin->getConfigForm($form, $form_state);
    $this->assertIsArray($config_form);
    $this->assertArrayHasKey('resource_id', $config_form);
    $this->assertIsArray($config_form['resource_id']['#options']);
    $this->assertCount(1, $config_form['resource_id']['#options']);
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
      'resource_id' => NULL,
    ], $additional_config);

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_webcontent_file', $configuration, $contexts);
  }

  /**
   * Mock a resource object.
   *
   * @return \Drupal\hpc_api\ApiObjects\Resource
   *   A resource object.
   */
  private function mockResource(int $id, string $name, string $uri, ?string $credits = NULL): Resource {
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn($uri);
    $url->setOptions(Argument::cetera())->willReturn(NULL);
    $resource = $this->prophesize(Resource::class);
    $resource->id()->willReturn($id);
    $resource->getName()->willReturn($name);
    $resource->getUrl()->willReturn($url->reveal());
    $resource->getCredit()->willReturn($credits);
    return $resource->reveal();
  }

}
