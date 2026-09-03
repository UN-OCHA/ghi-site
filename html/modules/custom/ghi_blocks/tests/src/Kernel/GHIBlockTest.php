<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ghi_blocks\Controller\BlockPreviewController;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\SectionStorageInterface;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\HttpFoundation\Request;

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
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
    'migrate',
    'hpc_api',
    'ghi_form_elements',
    'ghi_sections',
    'ghi_blocks',
    'ghi_base_objects',
    'ghi_blocks_test',
  ];

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

    $this->assertContains($plugin->getPluginId() . ':' . $plugin->getUuid(), $plugin->getCacheTags());
    $form_state->set('block', $plugin);
    $context_form = $this->callPrivateMethod($plugin, 'contextForm', [[], $form_state]);
    $this->assertCount(2, $context_form);
    $this->assertArrayHasKey('message', $context_form);

    $this->assertEquals('markup', $context_form['message']['#type']);
    $this->assertInstanceOf(FormattableMarkup::class, $context_form['message']['#markup']);

    $this->assertArrayHasKey('data_object', $context_form);
    $this->assertFalse($context_form['data_object']['#access']);

    $metadata = $plugin->buildMetaData();
    $this->assertCount(4, $metadata);
  }

  /**
   * Tests the defaults for a block without metadata.
   */
  public function testBlockPropertiesWithoutMetadata() {
    $plugin = $this->createBlockPlugin('ghi_blocks_current_uri_test', []);

    $this->assertNull($plugin->metadata());
    $this->assertTrue($plugin->shouldDisplayTitle());
    $this->assertFalse($plugin->hasDefaultTitle());
    $this->assertNull($plugin->getDefaultTitle());

    drupal_static_reset(GHIBlockBase::class . '::getSubforms');
    $subforms = $plugin->getSubforms();
    $this->assertCount(1, $subforms);
    $this->assertArrayHasKey('basic', $subforms);
    $this->assertSame('getConfigForm', $subforms['basic']['callback']);
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
   * Tests block content cache varies by current URI.
   */
  public function testBlockContentCacheVariesByCurrentUri() {
    $first_plugin = $this->createBlockPlugin('ghi_blocks_current_uri_test', []);
    $first_plugin->setCurrentUri('/plan/1189/ge/7460');
    $first_build = $first_plugin->doBuildContent();
    $this->assertSame('/plan/1189/ge/7460', $first_build[0]['#markup']);

    $second_plugin = $this->createBlockPlugin('ghi_blocks_current_uri_test', []);
    $second_plugin->setCurrentUri('/plan/1189/ge/7467');
    $second_build = $second_plugin->doBuildContent();
    $this->assertSame('/plan/1189/ge/7467', $second_build[0]['#markup']);
  }

  /**
   * Tests that optional titles are rendered when enabled.
   */
  public function testOptionalTitleBuild() {
    $plugin = $this->createBlockPlugin('ghi_blocks_optional_title_test', [
      'markup' => 'Test content',
    ], [], 'Optional title', TRUE);
    $build = $plugin->build();
    $this->assertEquals('Optional title', $build['#title']);
  }

  /**
   * Tests that override default titles are available on lazy builds.
   */
  public function testOverrideDefaultTitleLazyBuild() {
    $this->config('ghi_blocks.block_settings')
      ->set('lazy_load', TRUE)
      ->save();

    $plugin = $this->createBlockPlugin('ghi_blocks_override_default_title_test', [
      'markup' => 'Test content',
    ]);
    $build = $plugin->build();

    $this->assertEquals('Default override title', $build['#title']);
    $this->assertArrayHasKey('#lazy_builder', $build['content']);
  }

  /**
   * Tests that lazy titles are skipped when isEmpty() is not reliable.
   */
  public function testOverrideDefaultTitleLazyBuildSkipsUnreliableEmptyCheck() {
    $this->config('ghi_blocks.block_settings')
      ->set('lazy_load', TRUE)
      ->save();

    $plugin = $this->getDocumentLinksBlockPlugin();
    $build = $plugin->build();

    $this->assertArrayNotHasKey('#title', $build);
    $this->assertArrayHasKey('#lazy_builder', $build['content']);
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
    $this->assertArrayHasKey('#ghi_inline_errors_only', $configuration_form);
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
    $this->assertArrayHasKey('#ghi_inline_errors_only', $block_form);
    $this->assertFalse($block_form['actions']['subforms']['preview']['#limit_validation_errors']);
  }

  /**
   * Tests configuration previews rendered through the preview endpoint.
   */
  public function testBlockConfigurationPreviewUsesEndpoint() {
    $plugin = $this->getDatawrapperBlockPlugin(self::EMBED_CODE_VALID);
    $form_state = new FormState();
    $form_state->set('preview', TRUE);
    $configuration_form = $plugin->buildConfigurationForm([], $form_state);

    $this->assertArrayHasKey('preview', $configuration_form['container']);
    $preview = $configuration_form['container']['preview'];
    $attributes = $preview['#attributes'];
    $this->assertSame('generic_datawrapper', $attributes['data-block-preview']);
    $this->assertArrayHasKey('data-block-preview-token', $attributes);
    $this->assertArrayHasKey('data-block-preview-url', $attributes);
    $this->assertStringStartsWith('/block-preview/',
      $attributes['data-block-preview-url']);
    $this->assertArrayNotHasKey('content', $preview);

    $store = $this->container->get('keyvalue.expirable')
      ->get(GHIBlockBase::CONFIGURATION_PREVIEW_COLLECTION);
    $stored_preview = $store->get($attributes['data-block-preview-token']);
    $this->assertSame('generic_datawrapper', $stored_preview['plugin_id']);
    $this->assertSame(self::EMBED_CODE_VALID, $stored_preview['configuration']['hpc']['embed']);
  }

  /**
   * Tests that endpoint previews ignore undeclared stored contexts.
   */
  public function testBlockConfigurationPreviewEndpointSkipsUnknownContexts() {
    $plugin = $this->getDatawrapperBlockPlugin(self::EMBED_CODE_VALID);
    $configuration = $plugin->getConfiguration();
    $configuration['is_preview'] = TRUE;
    $token = $this->container->get('uuid')->generate();

    $store = $this->container->get('keyvalue.expirable')
      ->get(GHIBlockBase::CONFIGURATION_PREVIEW_COLLECTION);
    $store->setWithExpire($token, [
      'uid' => (int) $this->container->get('current_user')->id(),
      'plugin_id' => $plugin->getPluginId(),
      'configuration' => $configuration,
      'contexts' => [
        'year' => [
          'type' => 'scalar',
          'value' => 2025,
        ],
      ],
      'current_uri' => '/plan/1263/population',
    ], 3600);

    $controller = BlockPreviewController::create($this->container);
    $this->assertInstanceOf(AjaxResponse::class, $controller->preview($token));
  }

  /**
   * Tests that multi-step form values survive tabs, preview, and submit.
   */
  public function testMultistepValuesSurviveTabsPreviewAndSubmit() {
    $plugin = $this->getDocumentLinksBlockPlugin();
    $documents = [
      12 => [
        'item_type' => 'document_link',
        'config' => [
          'label' => 'Situation report',
          'url' => 'https://example.com/report.pdf',
        ],
      ],
    ];
    $publications_url = 'https://reliefweb.int/updates?advanced-search=%28PC13%29';

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'documents');
    $plugin->setFormState($form_state);

    // Initial form builds seed default values that must not override the first
    // submitted values from a tab switch.
    $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);

    $form_state->setValue(['documents', 'documents'], $documents);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'subforms', 'display'],
    ]);
    $element = [];
    GHIBlockBase::ajaxMultiStepSubmit($element, $form_state);

    $settings = $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);
    $this->assertSame('display', $form_state->get('current_subform'));
    $this->assertSame($documents, $settings['documents']['documents']);

    $form_state->setValues([
      'display' => [
        'publications_url' => $publications_url,
      ],
    ]);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'subforms', 'preview'],
      '#default_value' => FALSE,
    ]);
    $complete_form = [
      'settings' => [
        'container' => [],
      ],
    ];
    $form_state->setCompleteForm($complete_form);
    $plugin->blockElementValidate($element, $form_state);

    $settings = $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);
    $this->assertSame($documents, $settings['documents']['documents']);
    $this->assertSame($publications_url, $settings['display']['publications_url']);

    $form_state->setValues([]);
    $form_state->set('original_submit_handlers', []);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'submit'],
    ]);
    $form = [
      'actions' => [
        'submit' => [
          '#parents' => ['actions', 'submit'],
        ],
      ],
    ];
    $plugin->submitForm($form, $form_state);
    $plugin->blockSubmit($form, $form_state);

    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame($documents, $configuration['documents']['documents']);
    $this->assertSame($publications_url, $configuration['display']['publications_url']);
  }

  /**
   * Tests that submitted multi-step values win over remembered settings.
   */
  public function testMultistepSubmitUsesLatestVisibleTabValues() {
    $old_publications_url = 'https://reliefweb.int/updates?advanced-search=%28PC13%29';
    $new_publications_url = 'https://reliefweb.int/updates?advanced-search=%28PC13%29_%28F10%29';
    $plugin = $this->getDocumentLinksBlockPlugin([], $old_publications_url);

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'display');
    $form_state->set('original_submit_handlers', []);
    $plugin->setFormState($form_state);
    $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);

    $form_state->setValues([
      'display' => [
        'publications_url' => $new_publications_url,
      ],
    ]);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'submit'],
    ]);
    $form = [
      'actions' => [
        'submit' => [
          '#parents' => ['actions', 'submit'],
        ],
      ],
    ];
    $plugin->submitForm($form, $form_state);
    $plugin->blockSubmit($form, $form_state);

    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame($new_publications_url, $configuration['display']['publications_url']);
  }

  /**
   * Tests current URI resolution for Layout Builder editor requests.
   */
  public function testCurrentUriUsesEditorPaths() {
    $request_stack = $this->container->get('request_stack');

    $request_stack->push(Request::create('/layout-builder-ipe/entity/edit/overrides/node.17132', 'GET', [
      'current_path' => '/plan/1263/population',
    ]));
    $this->assertSame('/plan/1263/population', $this->getDatawrapperBlockPlugin()->getCurrentUri());
    $request_stack->pop();

    $request_stack->push(Request::create('/layout_builder/update/block/overrides/node.17132/0/content/example', 'GET', [
      'destination' => '/node/17132',
    ]));
    $this->assertSame('/node/17132', $this->getDatawrapperBlockPlugin()->getCurrentUri());
    $request_stack->pop();
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
