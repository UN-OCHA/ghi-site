<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CallToAction;
use Drupal\ghi_blocks_test\Form\LinkCarouselEditorialTestForm;
use Drupal\ghi_form_elements\Element\ConfigurationContainer;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests news and CTA editing through the Form API submission workflow.
 */
#[Group('ghi_blocks')]
class LinkCarouselEditorialFormTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'node', 'field', 'text', 'filter', 'file', 'image',
    'breakpoint', 'responsive_image', 'path_alias', 'page_manager',
    'ghi_blocks_test', 'inline_form_errors',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'node', 'file', 'path_alias'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'image', 'node', 'field', 'filter']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->container->set('current_route_match', $this->mockSectionStorageRouteMatch());
  }

  /**
   * Tests independent subforms and zero-ID rows in the configuration container.
   */
  public function testIndependentSubformsAndZeroId(): void {
    $plugin = $this->createEditorialBlock();
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $this->assertSame('links', $plugin->getDefaultSubform());
    $this->assertSame(['links', 'cta'], array_keys($plugin->getSubforms()));
    $items = $form['settings']['container']['items'];
    $this->assertSame('configuration_container', $items['#type']);
    $this->assertSame(['links', 'items'], $items['#parents']);
    $this->assertSame(LinkCarousel::MAX_FEATURED_ITEMS + 1, $items['summary_table']['#attributes']['data-link-carousel-max-items']);
    $this->assertArrayNotHasKey('selection', $form['settings']['container']);
    $stored = ConfigurationContainer::get($items, $state, 'items');
    $this->assertCount(6, $stored);
    $this->assertSame(0, $stored[0]['id']);
    $this->assertArrayNotHasKey('issues', $items['summary_table'][0]);
    $this->assertArrayNotHasKey('tag_line', $items['summary_table'][0]);
    $this->assertArrayHasKey('save_order', $items['actions']);
    $this->assertTrue($items['actions']['save_order']['#access']);
    $caption = (string) $items['summary_table']['#caption'];
    $this->assertStringContainsString('Items are displayed in list order.', $caption);
    $this->assertStringContainsString('Main news', $caption);
    $this->assertStringContainsString('Featured items', $caption);
    $this->assertStringContainsString('Invalid items are skipped', $caption);
    $separator = $items['summary_table']['_display_end'];
    $this->assertContains('draggable', $separator['#attributes']['class']);
    $this->assertArrayHasKey('data-link-carousel-display-end', $separator['#attributes']);
    $this->assertSame('', $separator['operations']['#markup']);
    $this->assertSame('', $separator['id']['#markup']);
    $this->assertFalse((bool) ($separator['id']['#input'] ?? FALSE));
    $this->assertContains('row-weight', $separator['weight']['#attributes']['class']);
    $this->assertArrayHasKey('links', $form['actions']['subforms']);
    $this->assertArrayHasKey('cta', $form['actions']['subforms']);
  }

  /**
   * Tests moving the separator and news across it retains pending boundaries.
   */
  public function testDisplayBoundaryMovesWithTableWeightsUntilFinalSave(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertSame([0, 1, 2, 3, 4, '_display_end', 5], Element::children($table, TRUE));
    $input = $this->createBoundaryInput([0, 1, '_display_end', 2, 3, 4, 5]);
    $this->switchSubform($plugin, $state, 'cta', $input);
    $this->assertSame([], $state->getErrors());
    $this->assertSame(2, $state->getTemporaryValue('links')['display_end_weight']);
    $this->assertSame(2, $state->get(['storage', 'links'])['display_end_weight']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $form = $this->switchSubform($plugin, $state, 'links');
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertPlacement($table[0], 'Main news');
    $this->assertPlacement($table[1], 'Featured 1');
    $this->assertPlacement($table[2], 'Not displayed');

    // Moving a news row across the boundary increases the displayed set without
    // changing the separator into a persisted configuration-container item.
    $form = $this->submitSaveOrder($plugin, $state, $this->createBoundaryInput([0, 1, 5, '_display_end', 2, 3, 4]));
    $this->assertSame([], $state->getErrors());
    $pending = ConfigurationContainer::get($form['settings']['container']['items'], $state, 'items');
    $this->assertSame([0, 1, 5, 2, 3, 4], array_column($pending, 'id'));
    $this->assertSame([0, 1, 2, 4, 5, 6], array_column($pending, 'weight'));
    $this->assertSame(3, $state->getTemporaryValue('links')['display_end_weight']);
    $this->assertCount(6, $pending);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->switchSubform($plugin, $state, 'cta');
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame(3, $saved['links']['display_end_weight']);
    $this->assertSame(array_values($pending), $saved['links']['items']);
    $this->assertCallToActionSettingsUnchanged($original['cta'], $saved['cta']);
    foreach (array_merge($saved['links']['items'], $saved['cta']['source_items']) as $item) {
      $this->assertNotNull(File::load($item['config']['value']['image'][0]));
    }
  }

  /**
   * Tests boundary clamping counts only available news, including row ID zero.
   */
  public function testDisplayBoundaryClampsAfterFirstAndFifthValidItems(): void {
    $maximum_plugin = $this->createEditorialBlock();
    $maximum_state = new FormState();
    $this->buildEditorialForm($maximum_plugin, $maximum_state);
    $input = $this->createBoundaryInput([0, 1, 2, 3, 4, 5, '_display_end']);
    $form = $this->submitSaveOrder($maximum_plugin, $maximum_state, $input);
    $this->assertSame(5, $maximum_state->getTemporaryValue('links')['display_end_weight']);
    $this->assertPlacement($form['settings']['container']['items']['summary_table'][5], 'Not displayed');

    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['links']['items'][0]['config']['value']['url'] = 'unfinished destination';
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertPlacement($table[0], 'Not available', FALSE);
    $form = $this->submitSaveOrder($plugin, $state, $this->createBoundaryInput(['_display_end', 0, 1, 2, 3, 4, 5]));
    $this->assertSame([], $state->getErrors());
    $this->assertSame(2, $state->getTemporaryValue('links')['display_end_weight']);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertPlacement($table[0], 'Not available', FALSE);
    $this->assertPlacement($table[1], 'Main news');
    $this->assertPlacement($table[2], 'Not displayed');
    $form = $this->submitSaveOrder($plugin, $state, $this->createBoundaryInput([0, 1, 2, 3, 4, 5, '_display_end']));
    $this->assertSame(6, $state->getTemporaryValue('links')['display_end_weight']);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertPlacement($table[5], 'Featured 4');
    $this->assertSame($configuration['hpc'], $plugin->getConfiguration()['hpc']);
    $this->submitEditorialForm($plugin, $state, []);
    $saved = $plugin->getConfiguration()['hpc']['links'];
    $this->assertSame(6, $saved['display_end_weight']);
    $this->assertSame([0, 1, 2, 3, 4, 5], array_column($saved['items'], 'id'));
    $this->assertSame('unfinished destination', $saved['items'][0]['config']['value']['url']);
  }

  /**
   * Tests empty and entirely invalid lists save without validation errors.
   */
  public function testDisplayBoundaryAllowsEmptyAndEntirelyInvalidLists(): void {
    foreach ([[], [0, 1]] as $ids) {
      $items = [];
      foreach ($ids as $id) {
        $items[$id] = $this->createNewsItem($id);
        $items[$id]['config']['value']['url'] = 'unfinished destination';
      }
      $plugin = $this->createBlockPlugin('generic_link_carousel', ['links' => ['items' => $items]]);
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      if ($items) {
        $this->submitSaveOrder($plugin, $state, $this->createBoundaryInput([0, '_display_end', 1]));
        $this->assertSame(1, $state->getTemporaryValue('links')['display_end_weight']);
      }
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame([], $state->getErrors());
      $saved = $plugin->getConfiguration()['hpc']['links']['items'];
      $this->assertSame($ids, array_column($saved, 'id'));
      $this->assertNull($plugin->buildContent()['#highlighted']);
      $this->assertSame([], $plugin->buildContent()['#featured']);
    }
  }

  /**
   * Tests the first valid addition is immediately inside the display boundary.
   */
  public function testFirstValidNewsAddedToEmptyListIsDisplayed(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', ['links' => ['items' => []]]);
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $news = $this->createNewsItem(10);

    $form = $this->submitNewsItem($plugin, $state, $news['config']);

    $this->assertSame([], $state->getErrors());
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertSame([1, '_display_end'], Element::children($table, TRUE));
    $this->assertPlacement($table[1], 'Main news');
    $this->assertSame(1, $plugin->getBlockConfig()['links']['display_end_weight']);
    $this->assertSame([], $plugin->getConfiguration()['hpc']['links']['items']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame('Article 10', $plugin->buildContent()['#highlighted']['title']);
    $this->assertSame([], $plugin->buildContent()['#featured']);
    $this->assertNotNull(File::load($news['config']['value']['image'][0]));
  }

  /**
   * Tests repairing the first draft does not require moving its boundary.
   */
  public function testFirstAddedDraftCanBeRepairedAboveTheSameBoundary(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', ['links' => ['items' => []]]);
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $news = $this->createNewsItem(10);
    $draft = $news['config'];
    $draft['value']['url'] = 'unfinished destination';
    $form = $this->submitNewsItem($plugin, $state, $draft);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertSame([1, '_display_end'], Element::children($table, TRUE));
    $this->assertPlacement($table[1], 'Not available', FALSE);
    $this->assertSame(1, $plugin->getBlockConfig()['links']['display_end_weight']);
    $this->assertNull($plugin->buildContent()['#highlighted']);

    $form = $this->submitNewsItem($plugin, $state, $news['config'], 1);

    $this->assertSame([], $state->getErrors());
    $this->assertPlacement($form['settings']['container']['items']['summary_table'][1], 'Main news');
    $this->assertSame(1, $plugin->getBlockConfig()['links']['display_end_weight']);
    $this->switchSubform($plugin, $state, 'cta');
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame('Article 10', $plugin->buildContent()['#highlighted']['title']);
    $this->assertSame(1, $plugin->getConfiguration()['hpc']['links']['display_end_weight']);
  }

  /**
   * Tests additions preserve full and deliberately shorter pending selections.
   */
  public function testLaterNewsAppendsBelowExistingDisplayBoundary(): void {
    foreach ([NULL, 2] as $boundary) {
      $plugin = $this->createEditorialBlock($boundary);
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      $original = $plugin->getConfiguration()['hpc'];
      $this->switchSubform($plugin, $state, 'cta');
      $cta = $this->createCallToActionConfiguration();
      $cta['items'][0]['config']['label'] = 'Donation edited before adding news';
      $this->submitCallToActionItem($plugin, $state, $cta['items'][0]['config'], 1);
      $this->switchSubform($plugin, $state, 'links');
      $before = $plugin->buildContent();
      $news = $this->createNewsItem(10);

      $form = $this->submitNewsItem($plugin, $state, $news['config']);

      $this->assertSame([], $state->getErrors());
      $pending = $plugin->getBlockConfig();
      $this->assertSame($boundary ?? 5, $pending['links']['display_end_weight']);
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
      $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_column($pending['links']['items'], 'id'));
      $this->assertGreaterThan($pending['links']['display_end_weight'], $pending['links']['items'][6]['weight']);
      $this->assertPlacement($form['settings']['container']['items']['summary_table'][6], 'Not displayed');
      $after = $plugin->buildContent();
      $this->assertSame($before['#highlighted']['title'], $after['#highlighted']['title']);
      $this->assertSame(array_column($before['#featured'], 'title'), array_column($after['#featured'], 'title'));
      $this->switchSubform($plugin, $state, 'cta');
      $this->submitEditorialForm($plugin, $state, []);
      $saved = $plugin->getConfiguration()['hpc'];
      $this->assertSame(array_values($pending['links']['items']), $saved['links']['items']);
      $this->assertSame($cta['items'][0]['config']['label'], $saved['cta']['items'][0]['config']['label']);
      $this->assertNotNull(File::load($news['config']['value']['image'][0]));
    }
  }

  /**
   * Tests legacy data is adapted without writes and survives either-tab saves.
   */
  public function testLegacyConfigurationCanSaveFromEitherSubformWithoutDataLoss(): void {
    $this->container->set('current_route_match', $this->mockSectionStorageRouteMatch('overrides'));
    foreach (['links', 'cta'] as $subform) {
      $plugin = $this->createLegacyBlock();
      $original = $plugin->getConfiguration()['hpc'];
      $source = $original['items'][90];
      $effective = $plugin->getBlockConfig();
      $this->assertSame([0, 1, 2, 3, 4, 5], array_values(array_column($effective['links']['items'], 'id')));
      $this->assertSame([$source], array_values($effective['cta']['source_items']));
      $this->assertSame('Archived donation title', $effective['cta']['items'][0]['config']['label']);
      $this->assertSame('Archived donation description', $effective['cta']['items'][0]['config']['value']['description']);
      $this->assertSame('Support now', $effective['cta']['items'][0]['config']['value']['button_label']);
      $build = $plugin->buildContent();
      $this->assertSame('Article 0', $build['#highlighted']['title']);
      $this->assertCount(4, $build['#featured']);
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
      $owner = PageVariant::create(['id' => 'legacy_editorial_file_owner']);
      $plugin->postSave($owner, $plugin->getUuid());

      $state = new FormState();
      $form = $this->buildEditorialForm($plugin, $state);
      $this->assertSame(['links', 'cta'], array_keys($plugin->getSubforms()));
      $stored = ConfigurationContainer::get($form['settings']['container']['items'], $state, 'items');
      $this->assertSame([0, 1, 2, 3, 4, 5], array_values(array_column($stored, 'id')));
      if ($subform === 'cta') {
        $this->switchSubform($plugin, $state, $subform);
      }
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame([], $state->getErrors());
      $saved = $plugin->getConfiguration()['hpc'];
      $this->assertArrayNotHasKey('items', $saved);
      $this->assertNewsRecordsUnchanged(array_values($effective['links']['items']), $saved['links']['items']);
      $this->assertSame([$source], array_values($saved['cta']['source_items']));
      $this->assertSame('Archived donation title', $saved['cta']['items'][0]['config']['label']);
      $plugin->postSave($owner, $plugin->getUuid());
      foreach ($original['items'] as $item) {
        $file = File::load($item['config']['value']['image'][0]);
        $this->assertNotNull($file);
        $usage = $this->container->get('file.usage')->listUsage($file);
        $this->assertSame(1, (int) $usage['ghi_blocks']['page_variant:legacy_editorial_file_owner'][$plugin->getUuid()]);
      }
    }
  }

  /**
   * Tests missing CTA settings seed one active editable example.
   */
  public function testMissingCallToActionSettingsUseCompleteDefaults(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', ['items' => [$this->createNewsItem(0)]]);
    $original = $plugin->getConfiguration()['hpc'];
    $settings = $plugin->getBlockConfig();
    $this->assertSame(1, $settings['cta']['active_item_id']);
    $this->assertCount(1, $settings['cta']['items']);
    $record = $settings['cta']['items'][0]['config'];
    $this->assertNotSame('', trim($record['label']));
    foreach (['description', 'url', 'button_label'] as $key) {
      $this->assertNotSame('', trim($record['value'][$key]));
    }
    $this->assertTrue($record['value']['show_heart']);
    $this->assertSame([], $settings['cta']['source_items']);
    $this->assertSame($record['label'], $plugin->buildContent()['#call_to_action']['title']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
  }

  /**
   * Tests empty, inactive and incomplete CTA lists survive either-tab saves.
   */
  public function testExplicitCallToActionSettingsSurviveEitherSubformSave(): void {
    $inactive = $this->createCallToActionConfiguration();
    $inactive['active_item_id'] = NULL;
    $incomplete = $this->createCallToActionConfiguration();
    $incomplete['items'][0]['config']['label'] = '';
    $incomplete['items'][0]['config']['value']['url'] = 'unfinished destination';
    foreach (['links', 'cta'] as $subform) {
      foreach ([['items' => [], 'active_item_id' => NULL], $inactive, $incomplete] as $cta) {
        $plugin = $this->createLegacyBlock($cta);
        $effective = $plugin->getBlockConfig();
        $this->assertSame($cta['items'], $effective['cta']['items']);
        $this->assertSame($cta['active_item_id'], $effective['cta']['active_item_id']);
        $this->assertCount(1, $effective['cta']['source_items']);
        $this->assertNull($plugin->buildContent()['#call_to_action']);
        $state = new FormState();
        $this->buildEditorialForm($plugin, $state);
        if ($subform === 'cta') {
          $this->switchSubform($plugin, $state, $subform);
        }
        $this->submitEditorialForm($plugin, $state, []);
        $this->assertSame([], $state->getErrors());
        $saved = $plugin->getConfiguration()['hpc']['cta'];
        $this->assertSame(array_values($cta['items']), $saved['items']);
        $this->assertSame($cta['active_item_id'], $saved['active_item_id']);
        $this->assertSame($effective['cta']['source_items'], $saved['source_items']);
      }
    }
  }

  /**
   * Tests explicitly empty news overrides the original item storage.
   */
  public function testCanonicalEmptyNewsOverridesOriginalItems(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'items' => [$this->createNewsItem(0)],
      'links' => ['items' => []],
      'cta' => ['items' => [], 'active_item_id' => NULL],
    ]);
    $original = $plugin->getConfiguration()['hpc'];
    $this->assertSame([], $plugin->getBlockConfig()['links']['items']);
    $this->assertNull($plugin->buildContent()['#highlighted']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $this->assertArrayHasKey('cta', $form['actions']['subforms']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame([], $saved['links']['items']);
    $this->assertArrayNotHasKey('items', $saved);
  }

  /**
   * Tests drag weights are saved without the separate Save order action.
   */
  public function testDragThenFinalSubmitRetainsDonationAndInactiveImages(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $owner = PageVariant::create(['id' => 'editorial_file_owner']);
    $plugin->postSave($owner, $plugin->getUuid());
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $this->submitEditorialForm($plugin, $state, $this->createReorderedInput($original['links']['items']));

    $this->assertSame([], $state->getErrors());
    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame([5, 0, 1, 2, 3, 4], array_values(array_column($configuration['links']['items'], 'id')));
    $this->assertCount(6, $configuration['links']['items']);
    $this->assertCallToActionSettingsUnchanged($original['cta'], $configuration['cta']);
    $plugin->postSave($owner, $plugin->getUuid());
    foreach (array_merge($original['links']['items'], $original['cta']['source_items']) as $item) {
      $file = File::load($item['config']['value']['image'][0]);
      $this->assertNotNull($file);
      $usage = $this->container->get('file.usage')->listUsage($file);
      $this->assertSame(1, (int) $usage['ghi_blocks']['page_variant:editorial_file_owner'][$plugin->getUuid()]);
    }
  }

  /**
   * Tests Save order rebuilds pending placements without saving the element.
   */
  public function testSaveOrderRetainsPendingNewsAndDonationEdits(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $this->submitSaveOrder($plugin, $state, $this->createBoundaryInput([0, 1, 2, '_display_end', 3, 4, 5]));
    $this->switchSubform($plugin, $state, 'cta');
    $cta = $this->createCallToActionConfiguration();
    $cta['items'][0]['config']['label'] = 'Donation edited before saving order';
    $this->submitCallToActionItem($plugin, $state, $cta['items'][0]['config'], 1);
    $this->submitCallToActionOperation($plugin, $state, 1, 'deactivate');
    $form = $this->switchSubform($plugin, $state, 'links');

    $container = $form['settings']['container']['items'];
    ConfigurationContainer::set($container, $state, 'mode', 'edit_item');
    ConfigurationContainer::set($container, $state, 'edit_item', 5);
    $state->setTriggeringElement($container['summary_table'][5]['operations']['edit']);
    $state->setRequestMethod('POST');
    $state->setRebuild();
    $builder = $this->container->get('form_builder');
    $form = $builder->rebuildForm('link_carousel_editorial_test', $state);
    $button = $form['settings']['container']['items']['item_config']['actions']['submit_item'];
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($button);
    $state->setValues([
      'links' => [
        'items' => [
          'item_config' => [
            'plugin_config' => [
              'label' => 'News edited before saving order',
              'value' => [
                'image' => ['fids' => (string) $original['links']['items'][5]['config']['value']['image'][0]],
                'url' => 'https://example.com/edited-before-save-order',
                'description' => 'A pending description',
              ],
            ],
          ],
        ],
      ],
      $button['#name'] => $button['#value'],
    ]);
    $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
    $this->assertSame([], $state->getErrors());
    $state->setRebuild();
    $form = $builder->rebuildForm('link_carousel_editorial_test', $state);
    $container = $form['settings']['container']['items'];
    $pending = ConfigurationContainer::get($container, $state, 'items');
    $this->assertSame('News edited before saving order', $pending[5]['config']['label']);
    $this->assertSame(3, $state->getTemporaryValue('links')['display_end_weight']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);

    // Use the generic action's own handlers, not a direct setItems() call, so
    // validation must leave summary_table intact for elementSubmit().
    $button = $container['actions']['save_order'];
    $this->assertTrue($button['#access']);
    $this->assertSame([ConfigurationContainer::class, 'updateAjax'], $button['#ajax']['callback']);
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($button);
    $state->setValues($this->createReorderedInput($pending) + [$button['#name'] => $button['#value']]);
    $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
    $this->assertSame([], $state->getErrors());
    $this->assertTrue($state->isRebuilding());
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $form = $builder->rebuildForm('link_carousel_editorial_test', $state);
    $container = $form['settings']['container']['items'];
    $pending = ConfigurationContainer::get($container, $state, 'items');
    $this->assertSame([5, 0, 1, 2, 3, 4], array_values(array_column($pending, 'id')));
    $this->assertSame([0, 1, 2, 3, 5, 6], array_values(array_column($pending, 'weight')));
    $table = $container['summary_table'];
    $this->assertSame([5, 0, 1, 2, '_display_end', 3, 4], Element::children($table, TRUE));
    $this->assertPlacement($table[5], 'Main news');
    $this->assertPlacement($table[0], 'Featured 1');
    $this->assertPlacement($table[4], 'Not displayed');
    $by_id = array_column($pending, NULL, 'id');
    $this->assertSame('News edited before saving order', $by_id[5]['config']['label']);
    $this->assertSame('A pending description', $by_id[5]['config']['value']['description']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);

    $response = ConfigurationContainer::updateAjax($form, $state);
    $command = $response->getCommands()[0];
    $this->assertSame('insert', $command['command']);
    $this->assertSame('replaceWith', $command['method']);
    $this->assertSame('#' . $button['#ajax']['wrapper'], $command['selector']);
    $this->assertStringContainsString('News edited before saving order', $command['data']);

    $form = $this->switchSubform($plugin, $state, 'cta');
    $this->assertNull($form['settings']['container']['active_item_id']['#value']);
    $pending_ctas = ConfigurationContainer::get($form['settings']['container']['items'], $state, 'items');
    $this->assertSame($cta['items'][0]['config']['label'], $pending_ctas[0]['config']['label']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame(4, $saved['links']['display_end_weight']);
    $this->assertSame(array_values($pending), $saved['links']['items']);
    $this->assertSame($cta['items'][0]['config']['label'], $saved['cta']['items'][0]['config']['label']);
    $this->assertNull($saved['cta']['active_item_id']);
    $this->assertSame($original['cta']['source_items'], $saved['cta']['source_items']);
  }

  /**
   * Tests a donation-tab save retains news edits and unsaved drag weights.
   */
  public function testDonationSubmitRetainsNewsEditsAndDragOrder(): void {
    $plugin = $this->createEditorialBlock(3);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $items = $plugin->getConfiguration()['hpc']['links']['items'];
    $items[0]['config']['label'] = 'An unsaved edited news title';
    ConfigurationContainer::setItems($form['settings']['container']['items'], $state, $items);
    $this->switchSubform($plugin, $state, 'cta', $this->createReorderedInput($items));
    $cta = $this->createCallToActionConfiguration();
    $cta['items'][0]['config']['label'] = 'An updated donation title';
    $cta['items'][0]['config']['value']['url'] = ' https://crisisrelief.un.org/en/donate ';
    $this->submitCallToActionItem($plugin, $state, $cta['items'][0]['config'], 1);
    $this->submitEditorialForm($plugin, $state, []);

    $this->assertSame([], $state->getErrors());
    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame(4, $configuration['links']['display_end_weight']);
    $this->assertSame([5, 0, 1, 2, 3, 4], array_values(array_column($configuration['links']['items'], 'id')));
    $by_id = array_column($configuration['links']['items'], NULL, 'id');
    $this->assertSame('An unsaved edited news title', $by_id[0]['config']['label']);
    $this->assertSame('An updated donation title', $configuration['cta']['items'][0]['config']['label']);
    $this->assertSame('https://crisisrelief.un.org/en/donate', $configuration['cta']['items'][0]['config']['value']['url']);
    $this->assertCount(1, $configuration['cta']['source_items']);
  }

  /**
   * Tests incomplete news remains editable and can be saved from either tab.
   */
  public function testIncompleteNewsCanSaveFromEitherSubform(): void {
    foreach (['links', 'cta'] as $subform) {
      foreach ([
        ['image' => [999999]],
        ['url' => 'javascript:alert(1)'],
        ['url' => 'ftp://example.com/news'],
        ['url' => 'internal:/missing-news-page'],
      ] as $invalid) {
        $plugin = $this->createEditorialBlock();
        $configuration = $plugin->getConfiguration();
        $configuration['hpc']['links']['items'][5]['config']['value'] = $invalid + $configuration['hpc']['links']['items'][5]['config']['value'];
        $plugin->setConfiguration($configuration);
        $state = new FormState();
        $state->clearErrors();
        $this->buildEditorialForm($plugin, $state);
        if ($subform === 'cta') {
          $this->switchSubform($plugin, $state, $subform);
        }
        $this->submitEditorialForm($plugin, $state, []);
        $this->assertSame([], $state->getErrors());
        $this->assertNewsRecordsUnchanged($configuration['hpc']['links']['items'], $plugin->getConfiguration()['hpc']['links']['items']);
        $cta = $plugin->getConfiguration()['hpc']['cta'];
        $this->assertCallToActionSettingsUnchanged($configuration['hpc']['cta'], $cta);
      }
    }
  }

  /**
   * Tests incomplete call-to-action records can be saved from either tab.
   */
  public function testIncompleteCallToActionCanSaveFromEitherSubform(): void {
    foreach (['links', 'cta'] as $subform) {
      $plugin = $this->createEditorialBlock();
      $configuration = $plugin->getConfiguration();
      $configuration['hpc']['cta']['items'][0]['config']['value']['url'] = 'javascript:alert(1)';
      $configuration['hpc']['cta']['items'][0]['config']['label'] = '';
      $plugin->setConfiguration($configuration);
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      if ($subform === 'cta') {
        $this->switchSubform($plugin, $state, $subform);
      }
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame([], $state->getErrors());
      $this->assertNewsRecordsUnchanged($configuration['hpc']['links']['items'], $plugin->getConfiguration()['hpc']['links']['items']);
      $this->assertCallToActionSettingsUnchanged($configuration['hpc']['cta'], $plugin->getConfiguration()['hpc']['cta']);
      $this->assertNull($plugin->buildContent()['#call_to_action']);
    }
  }

  /**
   * Tests activation and hiding stay pending until the element is saved.
   */
  public function testCallToActionSelectionPreservesAlternativesAndNews(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $news = $original['links']['items'];
    $news[0]['config']['label'] = 'Pending news edit';
    ConfigurationContainer::setItems($form['settings']['container']['items'], $state, $news);
    $form = $this->switchSubform($plugin, $state, 'cta');
    $this->assertSame('<strong>Choose one call-to-action (CTA) to display.</strong> Keep alternatives here and use the actions to switch between them or hide the panel. Incomplete items remain saved but are not displayed.', (string) $form['settings']['container']['caption']['#value']);
    $container = $form['settings']['container']['items'];
    $this->assertSame(['call_to_action' => []], $container['#allowed_item_types']);
    $this->assertEmpty($container['summary_table']['#tabledrag']);
    $this->assertArrayNotHasKey('save_order', $container['actions']);
    $this->assertSame('value', $form['settings']['container']['active_item_id']['#type']);
    $configuration = $this->createCallToActionConfiguration()['items'][0]['config'];
    $configuration['label'] = 'An alternative campaign';
    $configuration['value']['show_heart'] = FALSE;
    $form = $this->submitCallToActionItem($plugin, $state, $configuration);
    $pending = ConfigurationContainer::get($form['settings']['container']['items'], $state, 'items');
    $this->assertSame([1, 2], array_column($pending, 'id'));
    $this->assertSame(1, $plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->assertSame('call-to-action-activate-2', $form['settings']['container']['items']['summary_table'][2]['operations']['activate']['#name']);

    $form = $this->submitCallToActionOperation($plugin, $state, 2, 'activate');
    $this->assertSame(2, $plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertSame('An alternative campaign', $plugin->buildContent()['#call_to_action']['title']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->assertStringContainsString('Active', (string) $this->container->get('renderer')->renderInIsolation($form['settings']['container']['items']['summary_table'][2]['status']));

    $this->switchSubform($plugin, $state, 'links');
    $this->assertNewsRecordsUnchanged($news, $plugin->getBlockConfig()['links']['items']);
    $this->switchSubform($plugin, $state, 'cta');
    $this->submitCallToActionOperation($plugin, $state, 2, 'deactivate');
    $this->assertNull($plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertNull($plugin->buildContent()['#call_to_action']);
    $this->assertCount(2, $plugin->getBlockConfig()['cta']['items']);
    $this->submitEditorialForm($plugin, $state, []);
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertNull($saved['cta']['active_item_id']);
    $this->assertCount(2, $saved['cta']['items']);
    $this->assertNewsRecordsUnchanged($news, $saved['links']['items']);
    $this->assertSame($original['cta']['source_items'], $saved['cta']['source_items']);
  }

  /**
   * Tests a zero-ID CTA can be activated without being treated as missing.
   */
  public function testZeroIdCallToActionCanBeActivatedAndSaved(): void {
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['cta']['items'][0]['id'] = 0;
    $configuration['hpc']['cta']['active_item_id'] = NULL;
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $form = $this->switchSubform($plugin, $state, 'cta');
    $this->assertArrayHasKey(0, $form['settings']['container']['items']['summary_table']);
    $this->submitCallToActionOperation($plugin, $state, 0, 'activate');
    $this->assertSame(0, $plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertNotNull($plugin->buildContent()['#call_to_action']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $this->assertSame(0, $plugin->getConfiguration()['hpc']['cta']['active_item_id']);
    $this->assertCount(1, $plugin->getConfiguration()['hpc']['cta']['items']);
  }

  /**
   * Tests outer actions save current child edits as ordinary CTA records.
   */
  public function testOuterActionsWhileCallToActionEditorIsOpenKeepValidRecords(): void {
    foreach (['submit', 'links', 'preview'] as $action) {
      $plugin = $this->createEditorialBlock();
      $original = $plugin->getConfiguration()['hpc'];
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      $this->switchSubform($plugin, $state, 'cta');
      $form = $this->openCallToActionItem($plugin, $state, 1);
      $draft = $original['cta']['items'][0]['config'];
      $draft['label'] = 'Pending child title';
      $draft['value']['description'] = 'Pending child description';
      $draft['value']['show_heart'] = FALSE;
      $values = ['cta' => ['items' => ['item_config' => ['plugin_config' => $draft]]]];
      $values['cta']['items']['item_config']['plugin_config']['value']['show_heart'] = NULL;
      if ($action === 'links') {
        $this->switchSubform($plugin, $state, 'links', $values);
        $form = $this->switchSubform($plugin, $state, 'cta');
        $editor = $form['settings']['container']['items']['item_config']['plugin_config'];
        $this->assertSame($draft['label'], $editor['label']['#value']);
        $this->assertSame($draft['value']['description'], $editor['value']['description']['#value']);
      }
      elseif ($action === 'preview') {
        $form = $this->submitPreview($plugin, $state, $values);
        $this->assertTrue($state->get('preview'));
        $this->assertArrayHasKey('preview', $form['settings']['container']);
      }
      $this->submitEditorialForm($plugin, $state, $action === 'preview' ? [] : $values);
      $this->assertSame([], $state->getErrors());
      $saved = $plugin->getConfiguration()['hpc'];
      $this->assertSame([1], array_column($saved['cta']['items'], 'id'), $action);
      $this->assertSame(1, $saved['cta']['active_item_id'], $action);
      $this->assertSame($draft, $saved['cta']['items'][0]['config'], $action);
      $this->assertArrayNotHasKey('item_config', $saved['cta']['items']);
      $this->assertNewsRecordsUnchanged($original['links']['items'], $saved['links']['items']);
    }
  }

  /**
   * Tests leaving a new CTA editor creates one stable, inactive pending record.
   */
  public function testNewCallToActionEditorSurvivesTabRoundTripsWithoutDuplicates(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $form = $this->switchSubform($plugin, $state, 'cta');
    $this->submitContainerButton($plugin, $state, $form['settings']['container']['items']['actions']['add_new_item']);
    $draft = $original['cta']['items'][0]['config'];
    $draft['label'] = 'A new pending campaign';
    $draft['value']['description'] = 'A draft kept without choosing it for display.';
    $draft['value']['show_heart'] = FALSE;
    $values = ['cta' => ['items' => ['item_config' => ['plugin_config' => $draft]]]];
    $values['cta']['items']['item_config']['plugin_config']['value']['show_heart'] = NULL;
    for ($round_trip = 0; $round_trip < 2; $round_trip++) {
      $this->switchSubform($plugin, $state, 'links', $values);
      $pending = $plugin->getBlockConfig()['cta'];
      $this->assertSame([1, 2], array_column($pending['items'], 'id'));
      $this->assertSame(1, $pending['active_item_id']);
      $this->assertSame($draft, $pending['items'][1]['config']);
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
      $form = $this->switchSubform($plugin, $state, 'cta');
      $container = $form['settings']['container']['items'];
      $this->assertSame('edit_item', ConfigurationContainer::get($container, $state, 'mode'));
      $this->assertSame(2, ConfigurationContainer::get($container, $state, 'edit_item'));
      $this->assertSame($draft['label'], $container['item_config']['plugin_config']['label']['#value']);
    }
    $this->submitEditorialForm($plugin, $state, $values);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame([1, 2], array_column($saved['cta']['items'], 'id'));
    $this->assertSame(1, $saved['cta']['active_item_id']);
    $this->assertSame($draft, $saved['cta']['items'][1]['config']);
    $this->assertArrayNotHasKey('item_config', $saved['cta']['items']);
    $this->assertNewsRecordsUnchanged($original['links']['items'], $saved['links']['items']);
  }

  /**
   * Tests draft CTAs can be added, selected, repaired and removed normally.
   */
  public function testCallToActionDraftLifecycleDoesNotChooseAnotherItem(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $this->switchSubform($plugin, $state, 'cta');
    $configuration = $this->createCallToActionConfiguration()['items'][0]['config'];
    $configuration['label'] = '';
    $configuration['value']['url'] = 'javascript:alert(1)';
    $form = $this->submitCallToActionItem($plugin, $state, $configuration);
    $this->assertSame([], $state->getErrors());
    $this->assertSame(1, $plugin->getBlockConfig()['cta']['active_item_id']);
    $form = $this->submitCallToActionOperation($plugin, $state, 2, 'activate');
    $this->assertStringContainsString('Active — not displayed', (string) $this->container->get('renderer')->renderInIsolation($form['settings']['container']['items']['summary_table'][2]['status']));
    $this->assertNull($plugin->buildContent()['#call_to_action']);
    $configuration['label'] = 'Repaired campaign';
    $configuration['value']['url'] = ' HTTPS://example.com/campaign ';
    $form = $this->submitCallToActionItem($plugin, $state, $configuration, 2);
    $this->assertSame('Repaired campaign', $plugin->buildContent()['#call_to_action']['title']);
    $this->assertSame(2, $plugin->getBlockConfig()['cta']['active_item_id']);
    $form = $this->submitContainerButton($plugin, $state, $form['settings']['container']['items']['summary_table'][2]['operations']['remove']);
    $this->assertNull($plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertSame([1], array_column($plugin->getBlockConfig()['cta']['items'], 'id'));
    $this->assertNull($plugin->buildContent()['#call_to_action']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $this->assertNull($plugin->getConfiguration()['hpc']['cta']['active_item_id']);
    $this->assertCount(1, $plugin->getConfiguration()['hpc']['cta']['items']);
  }

  /**
   * Tests CTA child fields give non-blocking feedback for pending draft values.
   */
  public function testCallToActionFeedbackFollowsPendingEdits(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $this->switchSubform($plugin, $state, 'cta');
    $complete = $this->createCallToActionConfiguration()['items'][0]['config'];
    $messages = [
      'label' => 'Add a title',
      'description' => 'Add a description',
      'url' => 'Enter a valid URL',
      'button_label' => 'Add a button label',
    ];
    foreach ($messages as $key => $message) {
      $incomplete = $complete;
      if ($key === 'label') {
        $incomplete['label'] = '';
      }
      else {
        $incomplete['value'][$key] = $key === 'url' ? 'javascript:alert(1)' : '';
      }
      $form = $this->submitCallToActionItem($plugin, $state, $incomplete, 1);
      $form = $this->openCallToActionItem($plugin, $state, 1);
      $editor = $form['settings']['container']['items']['item_config']['plugin_config'];
      foreach (array_keys($messages) as $field) {
        $control = $field === 'label' ? $editor['label'] : $editor['value'][$field];
        $this->assertSame($field === $key, in_array('link-carousel-editor__field--unavailable', $control['#wrapper_attributes']['class'] ?? [], TRUE));
        $this->assertEmpty($control['#required'] ?? FALSE);
      }
      $control = $key === 'label' ? $editor['label'] : $editor['value'][$key];
      $markup = (string) $this->container->get('renderer')->renderInIsolation($control);
      $this->assertStringContainsString('Not available:', $markup);
      $this->assertStringContainsString($message, $markup);
      $this->assertStringContainsString('aria-describedby=', $markup);
      $this->assertSame([], $state->getErrors());
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
      $this->submitContainerButton($plugin, $state, $form['settings']['container']['items']['item_config']['actions']['cancel']);
    }
    $this->submitCallToActionItem($plugin, $state, $complete, 1);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertNotNull($plugin->buildContent()['#call_to_action']);
  }

  /**
   * Tests unavailable local CTA destinations remain editable without errors.
   */
  public function testCallToActionDestinationHighlightsRemainNonblocking(): void {
    $draft = Node::create(['type' => 'page', 'title' => 'Future campaign', 'status' => FALSE]);
    $draft->save();
    foreach ([
      'entity:node/' . $draft->id() => 'unpublished content',
      'internal:/admin' => 'not currently accessible to anonymous visitors',
    ] as $url => $message) {
      $plugin = $this->createEditorialBlock();
      $configuration = $plugin->getConfiguration();
      $configuration['hpc']['cta']['items'][0]['config']['value']['url'] = $url;
      $plugin->setConfiguration($configuration);
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      $this->switchSubform($plugin, $state, 'cta');
      $form = $this->openCallToActionItem($plugin, $state, 1);
      $editor = $form['settings']['container']['items']['item_config']['plugin_config'];
      $this->assertContains('link-carousel-editor__field--unavailable', $editor['value']['url']['#wrapper_attributes']['class']);
      $this->assertStringContainsString($message, (string) $editor['value']['url']['#description']);
      $this->assertNotContains('link-carousel-editor__field--unavailable', $editor['label']['#wrapper_attributes']['class'] ?? []);
      $this->assertSame([], $state->getErrors());
      $this->submitContainerButton($plugin, $state, $form['settings']['container']['items']['item_config']['actions']['cancel']);
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame($url, $plugin->getConfiguration()['hpc']['cta']['items'][0]['config']['value']['url']);
      $this->assertNull($plugin->buildContent()['#call_to_action']);
    }
  }

  /**
   * Tests hidden-trigger AJAX updates feedback without saving any child values.
   */
  public function testCallToActionFieldAjaxPreservesInputsAndPendingSettings(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration();
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $news = $original['hpc']['links']['items'];
    $news[0]['config']['label'] = 'An unsaved news edit';
    ConfigurationContainer::setItems($form['settings']['container']['items'], $state, $news);
    $this->switchSubform($plugin, $state, 'cta');
    $form = $this->openCallToActionItem($plugin, $state, 1);
    $editor = $form['settings']['container']['items']['item_config']['plugin_config'];
    foreach (['label', 'description', 'url', 'button_label'] as $key) {
      $field = $key === 'label' ? $editor['label'] : $editor['value'][$key];
      $this->assertArrayNotHasKey('#ajax', $field);
      $this->assertSame($key, $field['#wrapper_attributes']['data-link-carousel-cta-field']);
      $markup = (string) $this->container->get('renderer')->renderInIsolation($field);
      $this->assertStringContainsString('aria-live="polite"', $markup);
    }
    $feedback = $editor['feedback'];
    $this->assertSame('hidden', $feedback['#type']);
    $this->assertSame($feedback['#id'], $feedback['#attributes']['id']);
    $this->assertSame(['link_carousel_cta_feedback'], $feedback['#parents']);
    $this->assertContains(['link_carousel_cta_feedback'], $state->getCleanValueKeys());
    $this->assertSame('linkCarouselCtaFeedback', $feedback['#ajax']['event']);
    $this->assertSame([CallToAction::class, 'updateFeedback'], $feedback['#ajax']['callback']);
    $this->assertTrue($feedback['#ajax']['disable-refocus']);
    $this->assertFalse($feedback['#ajax']['refocus-blur']);
    $values = $original['hpc']['cta']['items'][0]['config'];
    foreach ([
      ['label', '', TRUE],
      ['label', 'A completed CTA title', FALSE],
      ['url', 'javascript:alert(1)', TRUE],
      ['url', 'https://example.com/action', FALSE],
    ] as [$key, $value, $unavailable]) {
      if ($key === 'label') {
        $values['label'] = $value;
      }
      else {
        $values['value'][$key] = $value;
      }
      $form = $this->submitCallToActionFieldChange($plugin, $state, $values, $key);
      $editor = $form['settings']['container']['items']['item_config']['plugin_config'];
      $this->assertFalse($state->isSubmitted());
      $this->assertFalse($state->isExecuted());
      $this->assertFalse($state->isCached());
      $this->assertTrue($state->getRebuildInfo()['copy']['#build_id']);
      $this->assertSame([], $state->getErrors());
      $this->assertArrayNotHasKey('link_carousel_cta_feedback', $state->cleanValues()->getValues());
      $callback = $state->getTriggeringElement()['#ajax']['callback'];
      $commands = $callback($form, $state)->getCommands();
      $selector = '#' . $form['#id'] . ':has(input[name="form_build_id"][value="' . $form['#build_id'] . '"])';
      $selector .= ' #' . $editor['#cta_feedback_wrapper_id'] . ' [data-link-carousel-cta-field="' . $key . '"]';
      $this->assertCount(2, $commands);
      $this->assertSame('invoke', $commands[0]['command']);
      $this->assertSame($selector, $commands[0]['selector']);
      $this->assertSame(['link-carousel-editor__field--unavailable', $unavailable], $commands[0]['args']);
      $this->assertSame($selector . ' [data-link-carousel-cta-feedback]', $commands[1]['selector']);
      $this->assertSame($unavailable, str_contains($commands[1]['data'], 'Not available:'));
      $this->assertStringNotContainsString('HTTP or HTTPS', $commands[1]['data']);
      $this->assertSame($original, $plugin->getConfiguration());
      $this->assertNewsRecordsUnchanged($news, $plugin->getBlockConfig()['links']['items']);
    }
    $form = $this->submitCallToActionFieldChange($plugin, $state, $values, 'unknown');
    $callback = $state->getTriggeringElement()['#ajax']['callback'];
    $this->assertSame([], $callback($form, $state)->getCommands());
    $button = $form['settings']['container']['items']['item_config']['actions']['submit_item'];
    $this->submitContainerButton($plugin, $state, $button, ['cta' => ['items' => ['item_config' => ['plugin_config' => $values]]]]);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame($values, $plugin->getConfiguration()['hpc']['cta']['items'][0]['config']);
    $this->assertArrayNotHasKey('link_carousel_cta_feedback', $plugin->getConfiguration()['hpc']);
  }

  /**
   * Tests final validation uses current values, not a cached feedback state.
   */
  public function testValidationUsesPassedStateInsteadOfCachedFeedbackState(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $stale = new FormState();
    $stale->setValues($original);
    $stale->setValue(['cta', 'items', 0, 'config', 'value', 'button_label'], 'An older feedback label');
    $plugin->setFormState($stale);
    $this->assertSame('An older feedback label', $plugin->getBlockConfig()['cta']['items'][0]['config']['value']['button_label']);
    $stale_values = $stale->getValues();
    $cta = $original['cta'];
    $cta['items'][0]['config']['value']['button_label'] = 'A freshly submitted CTA label';
    $news = $original['links'];
    $news['items'][0]['config']['label'] = 'A freshly submitted news title';
    $news['items'][0]['config']['value']['url'] = 'An unfinished news destination';
    $current = new FormState();
    $current->setValues(['links' => $news, 'cta' => $cta]);
    $current->setTriggeringElement(['#parents' => ['actions', 'submit']]);
    $plugin->blockValidate([], $current);
    $this->assertSame([], $current->getErrors());
    foreach (['links' => $news, 'cta' => $cta] as $key => $expected) {
      $this->assertSame($expected, $current->getValue($key));
      $this->assertSame($expected, $current->getTemporaryValue($key));
      $this->assertSame($expected, $current->get(['storage', $key]));
    }
    $this->assertSame($stale_values, $stale->getValues());
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
  }

  /**
   * Tests unavailable rows do not consume placements after table rebuilding.
   */
  public function testUnavailableRowsDoNotConsumePlacementsAfterReordering(): void {
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['links']['items'][0]['config']['label'] = '';
    $configuration['hpc']['links']['items'][0]['config']['value']['image'] = [];
    $configuration['hpc']['links']['items'][2]['config']['value']['url'] = 'javascript:alert(1)';
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertPlacement($table[0], 'Not available', FALSE);
    $this->assertPlacement($table[1], 'Main news');
    $this->assertPlacement($table[2], 'Not available', FALSE);
    $this->assertPlacement($table[3], 'Featured 1');
    $this->assertPlacement($table[4], 'Featured 2');
    $this->assertPlacement($table[5], 'Featured 3');
    $markup = (string) $this->container->get('renderer')->renderInIsolation($table[0]['placement']);
    $this->assertStringContainsString('Add a title', $markup);
    $this->assertStringContainsString('Add an image', $markup);

    // Tab navigation must capture drag weights without the Save order action.
    $this->switchSubform($plugin, $state, 'cta', $this->createReorderedInput($configuration['hpc']['links']['items']));
    $form = $this->switchSubform($plugin, $state, 'links');
    $table = $form['settings']['container']['items']['summary_table'];
    $this->assertSame([5, 0, 1, 2, 3, 4, '_display_end'], Element::children($table, TRUE));
    $this->assertPlacement($table[5], 'Main news');
    $this->assertPlacement($table[0], 'Not available', FALSE);
    $this->assertPlacement($table[1], 'Featured 1');
    $this->assertPlacement($table[2], 'Not available', FALSE);
    $this->assertPlacement($table[3], 'Featured 2');
    $this->assertPlacement($table[4], 'Featured 3');
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $this->assertCount(6, $plugin->getConfiguration()['hpc']['links']['items']);
  }

  /**
   * Tests tooltip descendants remain safe for complete-form error traversal.
   */
  public function testPlacementDecorationsRetainCompleteFormMetadata(): void {
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['links']['items'][0]['config']['label'] = '';
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $this->assertSame([], FormElementHelper::getElementByName('nonexistent', $form));

    // Upload and date validation can still fail even for non-blocking drafts.
    $state->setErrorByName('links][items', 'A failed image upload.');
    $this->container->get('form_error_handler')->handleFormErrors($form, $state);
    $this->assertNotEmpty($form['settings']['container']['items']['#errors']);
    $state->clearErrors();
  }

  /**
   * Tests an existing unpublished target can be configured before publication.
   */
  public function testDraftNewsTargetIsAccepted(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Draft campaign', 'status' => FALSE]);
    $node->save();
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['links']['items'][5]['config']['value']['url'] = 'entity:node/' . $node->id();
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $row = $form['settings']['container']['items']['summary_table'][5];
    $this->assertSame('0', $row['#attributes']['data-link-carousel-available']);
    $markup = (string) $this->container->get('renderer')->renderInIsolation($row['placement']);
    $this->assertStringContainsString('Not available', $markup);
    $this->assertStringContainsString('unpublished content', $markup);
    $this->submitEditorialForm($plugin, $state, ['links' => []]);
    $this->assertSame([], $state->getErrors());
    $this->assertSame('entity:node/' . $node->id(), $plugin->getConfiguration()['hpc']['links']['items'][5]['config']['value']['url']);
  }

  /**
   * Tests uppercase internal schemes have consistent editor and public ranks.
   */
  public function testUppercaseInternalUriHasConsistentPlacement(): void {
    $this->installConfig('user');
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    $node = Node::create(['type' => 'page', 'title' => 'Published campaign', 'status' => TRUE, 'uid' => 0]);
    $node->save();
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['links']['items'][0]['config']['value']['url'] = 'INTERNAL:/node/' . $node->id();
    $plugin->setConfiguration($configuration);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $this->assertPlacement($form['settings']['container']['items']['summary_table'][0], 'Main news');
    $build = $plugin->buildContent();
    $this->assertSame('Article 0', $build['#highlighted']['title']);
    $this->assertSame((string) $node->id(), $build['#highlighted']['button']['#url']->getRouteParameters()['node']);
    $this->assertContains('node:' . $node->id(), $build['#cache']['tags']);

    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $this->assertSame('internal:/node/' . $node->id(), $plugin->getConfiguration()['hpc']['links']['items'][0]['config']['value']['url']);
    $build = $plugin->buildContent();
    $this->assertSame('Article 0', $build['#highlighted']['title']);
    $this->assertSame((string) $node->id(), $build['#highlighted']['button']['#url']->getRouteParameters()['node']);
  }

  /**
   * Tests incomplete news can be added and edited without blocking saves.
   */
  public function testIncompleteChildItemsCanBeAddedAndEdited(): void {
    foreach (['add_item', 'edit_item'] as $mode) {
      foreach ([
        ['value' => ['image' => ['fids' => '']]],
        ['value' => ['image' => ['fids' => '999999']]],
        ['value' => ['url' => 'javascript:alert(1)']],
        ['label' => ''],
      ] as $invalid) {
        $plugin = $this->createEditorialBlock();
        $original = $plugin->getConfiguration()['hpc']['links']['items'];
        $state = new FormState();
        $state->clearErrors();
        $form = $this->buildEditorialForm($plugin, $state);
        $container = $form['settings']['container']['items'];
        $action = $mode === 'edit_item' ? $container['summary_table'][5]['operations']['edit'] : $container['actions']['add_new_item'];
        $form = $this->submitContainerButton($plugin, $state, $action);
        $builder = $this->container->get('form_builder');
        $item_form = $form['settings']['container']['items']['item_config'];
        $this->assertArrayNotHasKey('tag_line', $item_form['plugin_config']['value']);
        $button = $item_form['actions']['submit_item'];
        $value = ($invalid['value'] ?? []) + [
          'url' => 'https://example.com/updated-news',
          'image' => ['fids' => (string) $original[5]['config']['value']['image'][0]],
        ];
        $label = $invalid['label'] ?? 'An incomplete news draft';
        $state->setRebuild(FALSE);
        $state->setTriggeringElement($button);
        $state->setValues([
          'links' => [
            'items' => [
              'item_config' => ['plugin_config' => ['label' => $label, 'value' => $value]],
            ],
          ],
          $button['#name'] => $button['#value'],
        ]);
        $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
        $this->assertSame([], $state->getErrors());
        $stored = ConfigurationContainer::get($container, $state, 'items');
        $id = $mode === 'edit_item' ? 5 : 6;
        $this->assertCount($mode === 'edit_item' ? 6 : 7, $stored);
        $this->assertSame($label, $stored[$id]['config']['label']);
        $this->assertSame($value['url'], $stored[$id]['config']['value']['url']);
        $expected_images = $value['image']['fids'] === (string) $original[5]['config']['value']['image'][0] ? [$original[5]['config']['value']['image'][0]] : [];
        $this->assertSame($expected_images, $stored[$id]['config']['value']['image']);
        $this->assertArrayNotHasKey('tag_line', $stored[$id]['config']['value']);
        $this->assertSame($original, $plugin->getConfiguration()['hpc']['links']['items']);
        $this->switchSubform($plugin, $state, 'cta');
        $this->submitEditorialForm($plugin, $state, []);
        $this->assertSame([], $state->getErrors());
        $this->assertNewsRecordsUnchanged(array_values($stored), $plugin->getConfiguration()['hpc']['links']['items']);
      }
    }
  }

  /**
   * Tests saved invalid URIs can be reopened and saved unchanged.
   */
  public function testInvalidUrisRemainEditable(): void {
    foreach (['entity:node', 'entity:node/', 'javascript:alert(1)', 'internal:/missing-news-page'] as $uri) {
      $plugin = $this->createEditorialBlock();
      $configuration = $plugin->getConfiguration();
      $configuration['hpc']['links']['items'][5]['config']['value']['url'] = $uri;
      $plugin->setConfiguration($configuration);
      $state = new FormState();
      $form = $this->buildEditorialForm($plugin, $state);
      $container = $form['settings']['container']['items'];
      ConfigurationContainer::set($container, $state, 'mode', 'edit_item');
      ConfigurationContainer::set($container, $state, 'edit_item', 5);
      $state->setTriggeringElement($container['summary_table'][5]['operations']['edit']);
      $state->setRequestMethod('POST');
      $state->setRebuild();
      $builder = $this->container->get('form_builder');
      $form = $builder->rebuildForm('link_carousel_editorial_test', $state);
      $item_form = $form['settings']['container']['items']['item_config'];
      $this->assertSame($uri, $item_form['plugin_config']['value']['url']['#value']);
      $button = $item_form['actions']['submit_item'];
      $state->setRebuild(FALSE);
      $state->setTriggeringElement($button);
      $state->setValues([
        'links' => [
          'items' => [
            'item_config' => [
              'plugin_config' => [
                'label' => 'A draft with an unfinished destination',
                'value' => [
                  'image' => ['fids' => (string) $configuration['hpc']['links']['items'][5]['config']['value']['image'][0]],
                  'url' => $uri,
                ],
              ],
            ],
          ],
        ],
        $button['#name'] => $button['#value'],
      ]);
      $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
      $this->assertSame([], $state->getErrors());
      $stored = ConfigurationContainer::get($container, $state, 'items');
      $this->assertSame($uri, $stored[5]['config']['value']['url']);
      $this->switchSubform($plugin, $state, 'cta');
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame([], $state->getErrors());
      $saved = array_column($plugin->getConfiguration()['hpc']['links']['items'], NULL, 'id');
      $this->assertSame($uri, $saved[5]['config']['value']['url']);
    }
  }

  /**
   * Tests adding a draft-target news item and saving it from the donation tab.
   */
  public function testNewDraftItemSurvivesChildSubmitAndDonationSave(): void {
    $draft = Node::create(['type' => 'page', 'title' => 'Future campaign', 'status' => FALSE]);
    $draft->save();
    $plugin = $this->createEditorialBlock();
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $container = $form['settings']['container']['items'];
    $form = $this->submitContainerButton($plugin, $state, $container['actions']['add_new_item']);
    $builder = $this->container->get('form_builder');
    $item_form = $form['settings']['container']['items']['item_config'];
    $button = $item_form['actions']['submit_item'];
    $fid = $plugin->getConfiguration()['hpc']['links']['items'][0]['config']['value']['image'][0];
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($button);
    $state->setValues([
      'links' => [
        'items' => [
          'item_config' => [
            'plugin_config' => [
              'label' => 'A campaign prepared before publication',
              'value' => [
                'image' => ['fids' => (string) $fid],
                'url' => 'entity:node/' . $draft->id(),
              ],
            ],
          ],
        ],
      ],
      $button['#name'] => $button['#value'],
    ]);
    $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
    $this->assertSame([], $state->getErrors());
    $stored = ConfigurationContainer::get($container, $state, 'items');
    $this->assertCount(7, $stored);
    $this->assertSame(6, $stored[6]['id']);
    $this->assertSame('A campaign prepared before publication', $stored[6]['config']['label']);
    $this->assertCount(6, $plugin->getConfiguration()['hpc']['links']['items']);

    $this->switchSubform($plugin, $state, 'cta');
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $saved = array_column($plugin->getConfiguration()['hpc']['links']['items'], NULL, 'id');
    $this->assertCount(7, $saved);
    $this->assertSame('entity:node/' . $draft->id(), $saved[6]['config']['value']['url']);
  }

  /**
   * Tests unsupported CTA URLs survive normal item editing and final saves.
   */
  public function testIncompleteCallToActionFieldsAreRetained(): void {
    foreach (['not-a-url', 'javascript:alert(1)', 'ftp://example.com/action', 'internal:/missing-cta-page'] as $url) {
      $plugin = $this->createEditorialBlock();
      $state = new FormState();
      $this->buildEditorialForm($plugin, $state);
      $this->switchSubform($plugin, $state, 'cta');
      $config = $this->createCallToActionConfiguration()['items'][0]['config'];
      $config['value']['url'] = $url;
      $this->submitCallToActionItem($plugin, $state, $config, 1);
      $this->submitEditorialForm($plugin, $state, []);
      $this->assertSame([], $state->getErrors());
      $this->assertSame($url, $plugin->getConfiguration()['hpc']['cta']['items'][0]['config']['value']['url']);
    }
  }

  /**
   * Tests preview and a final submit without visible fields retain both tabs.
   */
  public function testPreviewThenFinalSubmitRetainsBothSubforms(): void {
    $plugin = $this->createEditorialBlock(3);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $items = $plugin->getConfiguration()['hpc']['links']['items'];
    $items[0]['config']['label'] = 'News edited before preview';
    ConfigurationContainer::setItems($form['settings']['container']['items'], $state, $items);
    $form = $this->switchSubform($plugin, $state, 'cta');
    $cta = $this->createCallToActionConfiguration();
    $cta['items'][0]['config']['label'] = 'Donation edited before preview';
    $form = $this->submitCallToActionItem($plugin, $state, $cta['items'][0]['config'], 1);
    $trigger = $form['actions']['subforms']['preview'];
    $state->setTriggeringElement($trigger);
    $state->setCompleteForm($form);
    $plugin->blockElementValidate($trigger, $state);
    $this->assertSame([], $state->getErrors());
    $form = $this->container->get('form_builder')->rebuildForm('link_carousel_editorial_test', $state);
    $this->assertTrue($state->get('preview'));
    $this->assertArrayHasKey('preview', $form['settings']['container']);
    $this->submitEditorialForm($plugin, $state, []);
    $this->assertSame([], $state->getErrors());
    $this->assertSame($items, $plugin->getConfiguration()['hpc']['links']['items']);
    $this->assertSame(3, $plugin->getConfiguration()['hpc']['links']['display_end_weight']);
    $this->assertSame('Donation edited before preview', $plugin->getConfiguration()['hpc']['cta']['items'][0]['config']['label']);
  }

  /**
   * Creates a block with six news items and a retained original donation image.
   */
  private function createEditorialBlock(?int $display_end_weight = NULL): LinkCarousel {
    $items = [];
    foreach (range(0, 5) as $id) {
      $items[$id] = $this->createNewsItem($id);
      if ($display_end_weight !== NULL && $id >= $display_end_weight) {
        $items[$id]['weight']++;
      }
    }
    $source = $this->createNewsItem(90);
    $source['config']['value']['url'] = 'https://crisisrelief.un.org/en/donate';
    $cta = $this->createCallToActionConfiguration();
    $cta['source_items'] = [$source];
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'links' => ['items' => $items, 'display_end_weight' => $display_end_weight],
      'cta' => $cta,
    ]);
    $plugin->setConfigurationValue('uuid', $this->container->get('uuid')->generate());
    return $plugin;
  }

  /**
   * Creates untouched legacy storage with the old donation slide ranked first.
   */
  private function createLegacyBlock(?array $cta = NULL): LinkCarousel {
    $source = $this->createNewsItem(90);
    $source['weight'] = -1;
    $source['config']['label'] = 'Archived donation title';
    $source['config']['value']['description'] = 'Archived donation description';
    $source['config']['value']['url'] = 'https://crisisrelief.un.org/en/donate';
    $source['config']['value']['button_label'] = 'Support now';
    $items = [90 => $source];
    foreach (range(0, 5) as $id) {
      $items[$id] = $this->createNewsItem($id);
    }
    $settings = ['items' => $items];
    if ($cta !== NULL) {
      $settings['cta'] = $cta;
    }
    $plugin = $this->createBlockPlugin('generic_link_carousel', $settings);
    $plugin->setConfigurationValue('uuid', $this->container->get('uuid')->generate());
    return $plugin;
  }

  /**
   * Creates a news configuration row with a real managed image entity.
   */
  private function createNewsItem(int $id): array {
    $file = File::create([
      'filename' => 'editorial-' . $id . '.png',
      'uri' => 'public://editorial-' . $id . '.png',
      'filemime' => 'image/png',
      'status' => TRUE,
    ]);
    $file->save();
    return [
      'id' => $id,
      'pid' => NULL,
      'item_type' => 'carousel_item',
      'weight' => $id,
      'config' => [
        'label' => 'Article ' . $id,
        'value' => [
          'image' => [$file->id()],
          'url' => 'https://example.com/article-' . $id,
          'description' => 'Description ' . $id,
          'tag_line' => 'An archived tag line ' . $id,
        ],
      ],
    ];
  }

  /**
   * Creates a CTA list with one active item and no archived source records.
   */
  private function createCallToActionConfiguration(): array {
    return [
      'items' => [
        [
          'id' => 1,
          'pid' => NULL,
          'weight' => 0,
          'item_type' => 'call_to_action',
          'config' => [
            'label' => 'Donate to relief organizations',
            'value' => [
              'description' => 'Support people affected by emergencies.',
              'url' => 'https://crisisrelief.un.org/en/donate',
              'button_label' => 'Donate now',
              'show_heart' => TRUE,
            ],
          ],
        ],
      ],
      'active_item_id' => 1,
      'source_items' => [],
    ];
  }

  /**
   * Creates posted table weights that move item ID five to the beginning.
   */
  private function createReorderedInput(array $items): array {
    $rows = [];
    foreach ($items as $item) {
      $rows[$item['id']] = ['id' => $item['id'], 'weight' => $item['id'] === 5 ? -1 : $item['weight']];
    }
    return ['links' => ['items' => ['summary_table' => $rows]]];
  }

  /**
   * Creates posted weights with the separator participating in the same order.
   */
  private function createBoundaryInput(array $order): array {
    $rows = [];
    foreach ($order as $weight => $id) {
      $rows[$id] = ['weight' => $weight];
      if ($id !== '_display_end') {
        $rows[$id]['id'] = $id;
      }
    }
    return ['links' => ['items' => ['summary_table' => $rows]]];
  }

  /**
   * Builds the plugin subform and initializes configuration-container state.
   */
  private function buildEditorialForm(LinkCarousel $plugin, FormState $state): array {
    return $this->container->get('form_builder')->buildForm(new LinkCarouselEditorialTestForm($plugin), $state);
  }

  /**
   * Submits a navigation button and rebuilds the requested GHI subform.
   */
  private function switchSubform(LinkCarousel $plugin, FormState $state, string $key, array $values = []): array {
    $button = $state->getCompleteForm()['actions']['subforms'][$key];
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($button);
    $state->setValues($values + [$button['#name'] => $button['#value']]);
    $builder = $this->container->get('form_builder');
    $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
    $state->setRequestMethod('POST');
    $state->setRebuild();
    return $builder->rebuildForm('link_carousel_editorial_test', $state);
  }

  /**
   * Submits Save order through the generic container's native handlers.
   */
  private function submitSaveOrder(LinkCarousel $plugin, FormState $state, array $values): array {
    $button = $state->getCompleteForm()['settings']['container']['items']['actions']['save_order'];
    return $this->submitContainerButton($plugin, $state, $button, $values);
  }

  /**
   * Adds or edits news through its real container buttons and child form.
   */
  private function submitNewsItem(LinkCarousel $plugin, FormState $state, array $configuration, ?int $id = NULL): array {
    $container = $state->getCompleteForm()['settings']['container']['items'];
    $button = $id === NULL ? $container['actions']['add_new_item'] : $container['summary_table'][$id]['operations']['edit'];
    $form = $this->submitContainerButton($plugin, $state, $button);
    $configuration['value']['image'] = ['fids' => implode(' ', $configuration['value']['image'] ?? [])];
    $button = $form['settings']['container']['items']['item_config']['actions']['submit_item'];
    return $this->submitContainerButton($plugin, $state, $button, [
      'links' => ['items' => ['item_config' => ['plugin_config' => $configuration]]],
    ]);
  }

  /**
   * Opens a saved CTA through its own edit action.
   */
  private function openCallToActionItem(LinkCarousel $plugin, FormState $state, int $id): array {
    $button = $state->getCompleteForm()['settings']['container']['items']['summary_table'][$id]['operations']['edit'];
    return $this->submitContainerButton($plugin, $state, $button);
  }

  /**
   * Adds or edits a CTA through its actual configuration-container child form.
   */
  private function submitCallToActionItem(LinkCarousel $plugin, FormState $state, array $configuration, ?int $id = NULL): array {
    $container = $state->getCompleteForm()['settings']['container']['items'];
    $button = $id === NULL ? $container['actions']['add_new_item'] : $container['summary_table'][$id]['operations']['edit'];
    $form = $this->submitContainerButton($plugin, $state, $button);
    $button = $form['settings']['container']['items']['item_config']['actions']['submit_item'];
    // FALSE asks a checkbox for its default; NULL submits it as unchecked.
    $configuration['value']['show_heart'] = !empty($configuration['value']['show_heart']) ? 1 : NULL;
    return $this->submitContainerButton($plugin, $state, $button, [
      'cta' => ['items' => ['item_config' => ['plugin_config' => $configuration]]],
    ]);
  }

  /**
   * Activates or hides a CTA through its server-built row action.
   */
  private function submitCallToActionOperation(LinkCarousel $plugin, FormState $state, int $id, string $operation): array {
    $button = $state->getCompleteForm()['settings']['container']['items']['summary_table'][$id]['operations'][$operation];
    return $this->submitContainerButton($plugin, $state, $button);
  }

  /**
   * Submits a processed container button and rebuilds the pending editor.
   */
  private function submitContainerButton(LinkCarousel $plugin, FormState $state, array $button, array $values = []): array {
    $this->assertSame('submit', $button['#type']);
    $this->assertNotEmpty($button['#parents']);
    $this->assertSame([ConfigurationContainer::class, 'updateAjax'], $button['#ajax']['callback']);
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($button);
    $state->setValues($values + [$button['#name'] => $button['#value']]);
    $builder = $this->container->get('form_builder');
    $builder->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
    $this->assertTrue($state->isRebuilding());
    $state->setRequestMethod('POST');
    return $builder->rebuildForm('link_carousel_editorial_test', $state);
  }

  /**
   * Processes a native non-button request using the cached subform state.
   */
  private function submitCallToActionFieldChange(LinkCarousel $plugin, FormState &$state, array $values, string $key): array {
    $previous_form = $state->getCompleteForm();
    $feedback = $previous_form['settings']['container']['items']['item_config']['plugin_config']['feedback'];
    $next_state = new FormState();
    $next_state->setFormState($state->getCacheableArray());
    $next_state->setTemporary($state->getTemporary());
    $next_state->setProgrammed(FALSE)->setRequestMethod('POST')->disableRedirect();
    $input = [
      'form_id' => 'link_carousel_editorial_test',
      '_triggering_element_name' => $feedback['#name'],
      'link_carousel_cta_feedback' => $key,
      'cta' => ['items' => ['item_config' => ['plugin_config' => $values]]],
    ];
    if (isset($previous_form['form_token']['#value'])) {
      $input['form_token'] = $previous_form['form_token']['#value'];
    }
    $next_state->setUserInput($input);
    $builder = $this->container->get('form_builder');
    $form_id = $builder->getFormId(new LinkCarouselEditorialTestForm($plugin), $next_state);
    $form = $builder->retrieveForm($form_id, $next_state);
    // Match a cached AJAX form's stable root ID without replacing the inputs.
    $form['#id'] = $previous_form['#id'];
    $builder->prepareForm($form_id, $form, $next_state);
    $builder->processForm($form_id, $form, $next_state);
    $state = $next_state;
    return $form;
  }

  /**
   * Processes the preview checkbox without rebuilding before input validation.
   */
  private function submitPreview(LinkCarousel $plugin, FormState &$state, array $values): array {
    $previous_form = $state->getCompleteForm();
    $next_state = new FormState();
    $next_state->setFormState($state->getCacheableArray());
    $next_state->setTemporary($state->getTemporary());
    $next_state->setProgrammed(FALSE)->setRequestMethod('POST')->disableRedirect();
    $input = $values + [
      'form_id' => 'link_carousel_editorial_test',
      '_triggering_element_name' => 'preview-toggle',
      'actions' => ['subforms' => ['preview' => 1]],
    ];
    if (isset($previous_form['form_token']['#value'])) {
      $input['form_token'] = $previous_form['form_token']['#value'];
    }
    $next_state->setUserInput($input);
    $builder = $this->container->get('form_builder');
    $form_id = $builder->getFormId(new LinkCarouselEditorialTestForm($plugin), $next_state);
    $form = $builder->retrieveForm($form_id, $next_state);
    $builder->prepareForm($form_id, $form, $next_state);
    $builder->processForm($form_id, $form, $next_state);
    $state = $next_state;
    return $form;
  }

  /**
   * Submits the element from the currently visible configuration tab.
   */
  private function submitEditorialForm(LinkCarousel $plugin, FormState $state, array $values): void {
    $state->setRebuild(FALSE);
    $state->setTriggeringElement(['#parents' => ['actions', 'submit']]);
    $state->setValues($values + ['op' => 'Save editorial content']);
    $this->container->get('form_builder')->submitForm(new LinkCarouselEditorialTestForm($plugin), $state);
  }

  /**
   * Checks a processed row's server-rendered placement and availability flag.
   */
  private function assertPlacement(array $row, string $label, bool $available = TRUE): void {
    $this->assertSame($available ? '1' : '0', $row['#attributes']['data-link-carousel-available']);
    $this->assertArrayNotHasKey('issues', $row);
    $this->assertNotEmpty($row['placement']['#parents']);
    $markup = (string) $this->container->get('renderer')->renderInIsolation($row['placement']);
    $this->assertStringContainsString($label, $markup);
    if (!$available) {
      $this->assertStringNotContainsString('Main news', $markup);
      $this->assertStringContainsString('link-carousel-editor__row--unavailable', implode(' ', $row['#attributes']['class']));
      $this->assertStringContainsString('tabindex="0"', $markup);
    }
  }

  /**
   * Checks retained news content independently of the shared row reindexing.
   */
  private function assertNewsRecordsUnchanged(array $expected, array $actual): void {
    // The separator joins the numeric order on save; only those weights change.
    $without_weights = static fn(array $item): array => array_diff_key($item, ['weight' => TRUE]);
    $this->assertSame(array_map($without_weights, $expected), array_map($without_weights, $actual));
  }

  /**
   * Checks CTA values strictly without relying on Form API property key order.
   */
  private function assertCallToActionSettingsUnchanged(array $expected, array $actual): void {
    $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual));
    foreach ($expected as $key => $value) {
      $this->assertSame($value, $actual[$key]);
    }
  }

  /**
   * Mocks the section-storage route used by Page Manager or node editors.
   */
  private function mockSectionStorageRouteMatch(string $plugin_id = 'page_manager'): RouteMatchInterface {
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getPluginId')->willReturn($plugin_id);
    $storage->method('getLayoutBuilderUrl')->willReturn(Url::fromUri('https://example.com/editor'));
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->willReturnCallback(static fn($name) => $name === 'section_storage' ? $storage : NULL);
    $route_match->method('getParameters')->willReturn(new ParameterBag(['section_storage' => $storage]));
    $route_match->method('getRawParameters')->willReturn(new ParameterBag());
    return $route_match;
  }

}
