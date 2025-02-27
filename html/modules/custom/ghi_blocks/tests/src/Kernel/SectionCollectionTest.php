<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Section\SectionCollection;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the section collection block plugin.
 *
 * @group ghi_blocks
 */
class SectionCollectionTest extends BlockKernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use SectionTestTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

    $this->createSectionType();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(SectionCollection::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(2, $allowed_item_types);
    $this->assertArrayHasKey('item_group', $allowed_item_types);
    $this->assertArrayHasKey('section_teaser', $allowed_item_types);

    $definition = $plugin->getPluginDefinition();
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $definition['config_forms']);

    $this->assertEquals('sections', $plugin->getDefaultSubform());
    $this->assertEquals('display', $plugin->getTitleSubform());
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->sectionsForm([], $form_state);
    $this->assertEquals('configuration_container', $form['items']['#type']);

    $form = $plugin->displayForm([], $form_state);
    $this->assertEquals([], $form);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
    $this->assertNull($plugin->buildContent());

    $plugin = $this->getBlockPlugin(0);
    $this->assertNull($plugin->buildContent());

    // Look at a block with 2 published nodes.
    $nodes = [
      $this->createSection(['status' => NodeInterface::PUBLISHED]),
      $this->createSection(['status' => NodeInterface::PUBLISHED]),
    ];
    $plugin = $this->getBlockPlugin($nodes);
    $build = $plugin->buildContent();
    $this->assertNotNull($build);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertArrayHasKey('tags', $build['#cache']);
    $this->assertNotEmpty($build['#cache']['tags']);
    $this->assertArrayHasKey(0, $build);
    $this->assertEquals('tab_container', $build[0]['#theme']);
    $this->assertCount(1, $build[0]['#tabs']);
    $tab = $build[0]['#tabs'][0];
    $this->assertNotEmpty($tab['title']['#markup']);
    $this->assertEquals('item_list', $tab['items']['#theme']);
    $this->assertCount(2, $tab['items']['#items']);
    $this->assertEquals('section-collection', $tab['items']['#attributes']['class'][0]);
    $this->assertEquals('section_collection', $tab['items']['#context']['plugin_type']);
    $this->assertEquals('section_collection', $tab['items']['#context']['plugin_id']);

    // Look at a block with 1 published node and 1 unpublished node.
    $nodes = [
      $this->createSection(['status' => NodeInterface::PUBLISHED]),
      $this->createSection(['status' => NodeInterface::NOT_PUBLISHED]),
    ];
    $plugin = $this->getBlockPlugin($nodes);
    $build = $plugin->buildContent();
    $tab = $build[0]['#tabs'][0];
    $this->assertCount(1, $tab['items']['#items']);
  }

  /**
   * Get a block plugin.
   *
   * @param array $nodes
   *   An array of nodes for which to create section teaser items.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Section\SectionCollection
   *   The block plugin.
   */
  private function getBlockPlugin($nodes = NULL) {
    $items = is_array($nodes) ? $this->buildSectionTeaserItems($nodes) : NULL;
    $configuration = [
      'sections' => [
        'items' => $items,
      ],
    ];
    return $this->createBlockPlugin('section_collection', $configuration);
  }

  /**
   * Build the section teaser items.
   *
   * @param array $nodes
   *   An array of nodes for which to create section teaser items.
   *
   * @return array
   *   An array of configuration items for section teasers.
   */
  private function buildSectionTeaserItems(array $nodes) {
    $items = [
      [
        'item_type' => 'item_group',
        'id' => 0,
        'config' => [
          'label' => 'Related plans',
        ],
        'weight' => 0,
        'pid' => NULL,
      ],
    ];
    foreach ($nodes as $node) {
      $items[] = [
        'item_type' => 'section_teaser',
        'id' => count($items),
        'config' => [
          'value' => $node->id(),
          'label' => $node->label(),
        ],
        'weight' => 0,
        'pid' => 0,
      ];
    }
    return $items;
  }

}
