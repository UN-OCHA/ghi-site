<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_content\Entity\Article;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\node\Entity\NodeType;

/**
 * Tests the sub-article renderer.
 *
 * @group ghi_content
 */
class SubArticleRendererTest extends KernelTestBase {

  use BaseObjectTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'layout_builder',
    'layout_discovery',
    'block',
    'migrate',
    'file',
    'filter',
    'hpc_api',
    'hpc_common',
    'ghi_base_objects',
    'ghi_blocks',
    'ghi_content_test',
    'ghi_content',
    'ghi_form_elements',
    'ghi_sections',
    'ghi_subpages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('base_object');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'node']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ])->enableLayoutBuilder()
      ->setOverridable()
      ->save();
  }

  /**
   * Tests that only the requested component slice is rendered.
   */
  public function testBuildRendersRequestedComponentSlice() {
    $article = Article::create([
      'type' => 'article',
      'title' => 'Local sub article',
      'status' => TRUE,
    ]);
    $article->set(OverridesSectionStorage::FIELD_NAME, [
      [
        'section' => new Section('layout_onecol', [], [
          $this->createComponent('component-1', 0, 'Component 1'),
          $this->createComponent('component-2', 1, 'Component 2'),
          $this->createComponent('component-3', 2, 'Component 3'),
          $this->createComponent('component-4', 3, 'Component 4'),
        ]),
      ],
    ]);
    $article->save();

    /** @var \Drupal\ghi_content\SubArticleRenderer $subarticle_renderer */
    $subarticle_renderer = $this->container->get('ghi_content.subarticle_renderer');
    $renderer = $this->container->get('renderer');

    $this->assertSame(4, $subarticle_renderer->countComponents($article));

    $preview_build = $subarticle_renderer->build($article, NULL, [], 0, 3);
    $preview = $renderer->renderRoot($preview_build);
    $this->assertStringContainsString('Component 1', (string) $preview);
    $this->assertStringContainsString('Component 2', (string) $preview);
    $this->assertStringContainsString('Component 3', (string) $preview);
    $this->assertStringNotContainsString('Component 4', (string) $preview);

    $deferred_build = $subarticle_renderer->build($article, NULL, [], 3);
    $deferred = $renderer->renderRoot($deferred_build);
    $this->assertStringNotContainsString('Component 1', (string) $deferred);
    $this->assertStringContainsString('Component 4', (string) $deferred);
  }

  /**
   * Tests that explicit base object context mappings are available.
   */
  public function testBuildResolvesComponentBaseObjectContextMapping() {
    $base_object_type = $this->createBaseObjectType([
      'id' => 'plan',
    ]);
    $this->createBaseObject([
      'type' => $base_object_type->id(),
      'name' => 'Mapped test plan',
      'field_original_id' => 1499,
    ]);

    $article = Article::create([
      'type' => 'article',
      'title' => 'Local sub article',
      'status' => TRUE,
    ]);
    $article->set(OverridesSectionStorage::FIELD_NAME, [
      [
        'section' => new Section('layout_onecol', [], [
          $this->createBaseObjectContextComponent('component-1', 0, 'plan--1499'),
        ]),
      ],
    ]);
    $article->save();

    /** @var \Drupal\ghi_content\SubArticleRenderer $subarticle_renderer */
    $subarticle_renderer = $this->container->get('ghi_content.subarticle_renderer');
    $renderer = $this->container->get('renderer');

    $build = $subarticle_renderer->build($article);
    $output = $renderer->renderRoot($build);
    $this->assertStringContainsString('Mapped plan context: Mapped test plan', (string) $output);
  }

  /**
   * Tests that nested widgets are lazy loaded from their own article's layout.
   *
   * @dataProvider parentLayoutContextProvider
   */
  public function testBuildLazyWidgetWithOwningArticle(bool $has_parent_context) {
    $this->createBaseObjectType(['id' => 'plan']);
    $this->config('ghi_blocks.block_settings')->set('lazy_load', TRUE)->save();
    $this->container->get('router.builder')->rebuild();

    $widget_url = 'https://app.powerbi.com/view?r=test-report';
    $component = new SectionComponent('widget-component', 'content', [
      'id' => 'generic_external_widget',
      'provider' => 'ghi_blocks',
      'hpc' => [
        'select_number' => 1,
        'widgets' => [
          [
            'widget_url' => $widget_url,
            'widget_height' => '600px',
          ],
        ],
      ],
    ]);
    $article = Article::create([
      'type' => 'article',
      'title' => 'Article containing a Power BI widget',
      'status' => TRUE,
    ]);
    $article->set(OverridesSectionStorage::FIELD_NAME, [
      ['section' => new Section('layout_onecol', [], [$component])],
    ]);
    $article->save();

    $contexts = [];
    if ($has_parent_context) {
      $parent = Article::create([
        'type' => 'article',
        'title' => 'Parent article',
        'status' => TRUE,
      ]);
      $parent->save();
      $contexts['layout_builder.entity'] = EntityContext::fromEntity($parent);
    }

    $subarticle_renderer = $this->container->get('ghi_content.subarticle_renderer');
    $this->assertSame(1, $subarticle_renderer->countComponents($article));
    $build = $subarticle_renderer->build($article, NULL, $contexts);
    $this->assertArrayHasKey('widget-component', $build);
    $lazy_builder = $build['widget-component']['content']['content']['#lazy_builder'];
    $this->assertSame($article->toUrl()->toString(), $lazy_builder[1][2]);

    $content = $lazy_builder[0](...$lazy_builder[1]);
    $output = $this->container->get('renderer')->renderRoot($content);
    $this->assertStringContainsString('<iframe', (string) $output);
    $this->assertStringContainsString($widget_url, (string) $output);
  }

  /**
   * Provides nested rendering contexts with and without a parent layout owner.
   */
  public function parentLayoutContextProvider(): array {
    return [
      'standalone sub-article' => [FALSE],
      'nested sub-article' => [TRUE],
    ];
  }

  /**
   * Create a layout builder section component.
   *
   * @param string $uuid
   *   The component UUID.
   * @param int $weight
   *   The component weight.
   * @param string $label
   *   The component label.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The section component.
   */
  private function createComponent($uuid, $weight, $label) {
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'system_powered_by_block',
      'label' => $label,
      'label_display' => TRUE,
      'provider' => 'system',
    ]);
    $component->setWeight($weight);
    return $component;
  }

  /**
   * Create a layout builder component with a stored base object context.
   *
   * @param string $uuid
   *   The component UUID.
   * @param int $weight
   *   The component weight.
   * @param string $plan_context
   *   The mapped plan context name.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The section component.
   */
  private function createBaseObjectContextComponent($uuid, $weight, $plan_context) {
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'ghi_content_test_base_object_context',
      'label' => 'Base object context',
      'label_display' => FALSE,
      'provider' => 'ghi_content_test',
      'context_mapping' => [
        'plan' => $plan_context,
      ],
    ]);
    $component->setWeight($weight);
    return $component;
  }

}
