<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\SectionStorageInterface;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Tests generic properties of block plugin.
 *
 * Testing against an instance of a datawrapper block.
 *
 * @group ghi_blocks
 */
class GHIBlockTest extends BlockKernelTestBase {

  const EMBED_CODE_VALID = '<iframe src="https://datawrapper.dwcdn.net/CHART_ID"></iframe>';
  const EMBED_CODE_INVALID = '<iframe src="https://invalid.url/CHART_ID"></iframe>';

  /**
   * Tests basic block properties on the example of a datawrapper block.
   */
  public function testBlockPropertiesDatawrapper() {
    $plugin = $this->getDatawrapperBlockPlugin();
    $this->assertInstanceOf(GHIBlockBase::class, $plugin);
    $this->assertNull($plugin->getData());

    $configuration = ['test' => 'test'];
    $this->callPrivateMethod($plugin, 'setBlockConfig', [$configuration]);
    $this->assertEquals($configuration, $plugin->getBlockConfig());

    $form_state = new FormState();
    $plugin->setFormState($form_state);

    $this->assertFalse($plugin->shouldDisplayTitle());
    $this->assertEquals('basic', $plugin->getTitleSubform());
    $this->assertFalse($plugin->hasDefaultTitle());
    $this->assertNull($plugin->getDefaultTitle());
    $this->assertEquals('<none>', $plugin->label());
    $this->assertEquals('"Datawrapper" block', $plugin->getPreviewFallbackString());
    $this->assertTrue($plugin->canShowSubform([], $form_state, 'test'));
    // @todo This should return FALSE for blocks like datawrapper.
    $this->assertFalse($plugin->needsContextConfiguration());
    $this->assertFalse($this->callPrivateMethod($plugin, 'canSelectBaseObject'));
    $this->assertCount(1, $plugin->getSubforms());
    $this->assertArrayHasKey('basic', $plugin->getSubforms());
    $this->assertIsString($this->callPrivateMethod($plugin, 'getContainerWrapper'));
    $this->assertFalse($plugin->isHidden());
    $this->assertFalse($this->callPrivateMethod($plugin, 'isPreview'));
    $this->assertFalse($plugin->isLayoutBuilder());
    $this->assertFalse($plugin->isLayoutBuilderFormSubmission());
    $this->assertFalse($this->callPrivateMethod($plugin, 'isConfigurationPreview'));
    $this->assertNull($plugin->getCurrentSectionNode());
    $this->assertNull($plugin->getCurrentBaseEntity());
    $this->assertNull($plugin->getCurrentBaseObject());
    $this->assertNull($plugin->getCurrentBaseObjectId());
    $this->assertNull($plugin->getCurrentPlanObject());
    $this->assertNull($plugin->getCurrentPlanId());
    $this->assertNull($plugin->getContextValue('test'));
    $this->assertNull($this->callPrivateMethod($plugin, 'getPageArgument', ['test']));
    $this->assertNull($plugin->getDownloadSource());
    $this->assertEmpty($plugin->getAvailableDownloadTypes());

    $cache_contexts = $plugin->getCacheContexts();
    $this->assertContains('url.path', $cache_contexts);
    $this->assertContains('url.query_args', $cache_contexts);
    $this->assertContains('user', $cache_contexts);

    $this->assertContains($plugin->getPluginId() . ':' . $plugin->getUuid(), $plugin->getCacheTags());
    $form_state->set('block', $plugin);
    $context_form = $this->callPrivateMethod($plugin, 'contextForm', [[], $form_state]);
    $this->assertCount(2, $context_form);
    $this->assertArrayHasKey('message', $context_form);

    $this->assertEquals('markup', $context_form['message']['#type']);
    $this->assertInstanceOf(FormattableMarkup::class, $context_form['message']['#markup']);

    $this->assertArrayHasKey('data_object', $context_form);
    $this->assertFalse($context_form['data_object']['#access']);

    $admin_icons = $plugin->getAdminIcons();
    $this->assertCount(0, $admin_icons);

    $metadata = $plugin->buildMetaData();
    $this->assertCount(4, $metadata);
  }

  /**
   * Tests basic block properties on the example of a  links block.
   */
  public function testBlockPropertiesLinks() {
    $plugin = $this->getLinksBlockPlugin();
    $this->assertInstanceOf(GHIBlockBase::class, $plugin);
    $this->assertInstanceOf(OptionalTitleBlockInterface::class, $plugin);
    $this->assertEquals('', $plugin->label());
  }

  /**
   * Tests basic block properties on the example of a documents links block.
   */
  public function testBlockPropertiesDocumentLinks() {
    $plugin = $this->getDocumentLinksBlockPlugin();
    $this->assertInstanceOf(GHIBlockBase::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertCount(2, $plugin->getSubforms());
    $this->assertArrayHasKey('documents', $plugin->getSubforms());
    $this->assertArrayHasKey('display', $plugin->getSubforms());
    $this->assertTrue($plugin->hasDefaultTitle());
    $this->assertEquals('Publications', $plugin->getDefaultTitle());
    $this->assertEquals('Publications', $plugin->label());
  }

  /**
   * Tests block build on the example of a datawrapper block.
   */
  public function testBlockBuild() {
    $plugin = $this->getDatawrapperBlockPlugin();
    $this->assertEmpty($plugin->build());

    $plugin = $this->getDatawrapperBlockPlugin(self::EMBED_CODE_VALID);
    $build = $plugin->build();
    // Catching exceptions here, otherwise a LogicException is thrown due to
    // the block build adding a class instance to the build array.
    try {
      $this->assertNotEmpty($build);
      $this->assertEquals($plugin, $build['#block_instance']);
    }
    catch (ExpectationFailedException $e) {
      fwrite(STDERR, $e->getComparisonFailure()->toString());
    }
  }

  /**
   * Tests block configuration form on the example of a datawrapper block.
   */
  public function testBlockConfigurationForm() {
    $plugin = $this->getDatawrapperBlockPlugin();
    $form_state = new FormState();
    $configuration_form = $plugin->buildConfigurationForm([], $form_state);

    $this->assertArrayHasKey('provider', $configuration_form);
    $this->assertArrayHasKey('admin_label', $configuration_form);
    $this->assertArrayHasKey('label', $configuration_form);
    $this->assertArrayHasKey('label_display', $configuration_form);
    $this->assertArrayHasKey('container', $configuration_form);
    $this->assertArrayHasKey('context_mapping', $configuration_form);
    $this->assertArrayHasKey('#ghi_modal_form', $configuration_form);
    $this->assertArrayHasKey('provider', $configuration_form);

    $this->assertArrayHasKey('label', $configuration_form['container']);
    $this->assertArrayHasKey('label_display', $configuration_form['container']);
    $this->assertArrayHasKey('embed', $configuration_form['container']);
    $this->assertArrayHasKey('context_mapping', $configuration_form['container']);

    $block_form = [
      'settings' => $configuration_form,
    ];

    $route_match = $this->prophesize(RouteMatchInterface::class);
    $route_match->getParameter('section_storage')->willReturn($this->prophesize(SectionStorageInterface::class)->reveal());
    \Drupal::getContainer()->set('current_route_match', $route_match->reveal());
    $plugin = $this->getDatawrapperBlockPlugin();

    $form_state->setBuildInfo(['callback_object' => NULL]);
    $block_form['#submit'] = [];
    $plugin->blockFormAlter($block_form, $form_state);
    $this->assertContains('generic-datawrapper', $block_form['#attributes']['class']);
  }

  /**
   * Get a datawrapper block plugin.
   *
   * @param array $embed
   *   The embed code for the plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\Datawrapper
   *   The block plugin.
   */
  private function getDatawrapperBlockPlugin($embed = '') {
    $configuration = [
      'embed' => $embed,
    ];
    return $this->createBlockPlugin('generic_datawrapper', $configuration);
  }

  /**
   * Get a block plugin.
   *
   * @param array $documents
   *   The documents configuration to add to the plugin.
   * @param string $publications_url
   *   The url where external publications can be found.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinks
   *   The block plugin.
   */
  private function getDocumentLinksBlockPlugin($documents = [], $publications_url = '') {
    $configuration = [
      'documents' => [
        'documents' => $documents,
      ],
      'display' => [
        'publications_url' => $publications_url,
      ],
    ];
    return $this->createBlockPlugin('generic_document_links', $configuration);
  }

  /**
   * Get a block plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\Links
   *   The block plugin.
   */
  private function getLinksBlockPlugin() {
    $configuration = ['links' => ['links' => []]];
    return $this->createBlockPlugin('links', $configuration);
  }

}
