<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
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
 * Tests links and donation editing through the Form API submission workflow.
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
    $this->assertSame(['links', 'donation'], array_keys($plugin->getSubforms()));
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
    $this->assertArrayHasKey('donation', $form['actions']['subforms']);
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
    $this->switchSubform($plugin, $state, 'donation', $input);
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
    $this->switchSubform($plugin, $state, 'donation');
    $this->submitEditorialForm($plugin, $state, ['donation' => $this->createDonationValues()]);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame(3, $saved['links']['display_end_weight']);
    $this->assertSame(array_values($pending), $saved['links']['items']);
    $saved['donation']['enabled'] = (bool) $saved['donation']['enabled'];
    $this->assertSame($original['donation'], $saved['donation']);
    foreach (array_merge($saved['links']['items'], $saved['donation']['source_items']) as $item) {
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
    $this->switchSubform($plugin, $state, 'donation');
    $this->submitEditorialForm($plugin, $state, ['donation' => $this->createDonationValues()]);
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
      $this->switchSubform($plugin, $state, 'donation');
      $donation = $this->createDonationValues();
      $donation['title'] = 'Donation edited before adding news';
      $this->switchSubform($plugin, $state, 'links', ['donation' => $donation]);
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
      $this->switchSubform($plugin, $state, 'donation');
      $this->submitEditorialForm($plugin, $state, ['donation' => $donation]);
      $saved = $plugin->getConfiguration()['hpc'];
      $this->assertSame(array_values($pending['links']['items']), $saved['links']['items']);
      $this->assertSame($donation['title'], $saved['donation']['title']);
      $this->assertNotNull(File::load($news['config']['value']['image'][0]));
    }
  }

  /**
   * Tests legacy data is adapted without writes and survives either-tab saves.
   */
  public function testLegacyConfigurationCanSaveFromEitherSubformWithoutDataLoss(): void {
    $this->container->set('current_route_match', $this->mockSectionStorageRouteMatch('overrides'));
    foreach (['links', 'donation'] as $subform) {
      $plugin = $this->createLegacyBlock();
      $original = $plugin->getConfiguration()['hpc'];
      $source = $original['items'][90];
      $effective = $plugin->getBlockConfig();
      $this->assertSame([0, 1, 2, 3, 4, 5], array_values(array_column($effective['links']['items'], 'id')));
      $this->assertSame([$source], array_values($effective['donation']['source_items']));
      $this->assertSame('Archived donation title', $effective['donation']['title']);
      $this->assertSame('Archived donation description', $effective['donation']['description']);
      $this->assertSame('Support now', $effective['donation']['button_label']);
      $build = $plugin->buildContent();
      $this->assertSame('Article 0', $build['#highlighted']['title']);
      $this->assertCount(4, $build['#featured']);
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
      $owner = PageVariant::create(['id' => 'legacy_editorial_file_owner']);
      $plugin->postSave($owner, $plugin->getUuid());

      $state = new FormState();
      $form = $this->buildEditorialForm($plugin, $state);
      $this->assertSame(['links', 'donation'], array_keys($plugin->getSubforms()));
      $stored = ConfigurationContainer::get($form['settings']['container']['items'], $state, 'items');
      $this->assertSame([0, 1, 2, 3, 4, 5], array_values(array_column($stored, 'id')));
      if ($subform === 'donation') {
        $this->switchSubform($plugin, $state, $subform);
      }
      $values = $subform === 'donation' ? ['donation' => array_diff_key($effective['donation'], ['source_items' => TRUE])] : [];
      $this->submitEditorialForm($plugin, $state, $values);
      $this->assertSame([], $state->getErrors());
      $saved = $plugin->getConfiguration()['hpc'];
      $this->assertArrayNotHasKey('items', $saved);
      $this->assertNewsRecordsUnchanged(array_values($effective['links']['items']), $saved['links']['items']);
      $this->assertSame([$source], array_values($saved['donation']['source_items']));
      $this->assertSame('Archived donation title', $saved['donation']['title']);
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
   * Tests missing donation settings receive a complete standard panel.
   */
  public function testMissingDonationSettingsUseCompleteDefaults(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', ['items' => [$this->createNewsItem(0)]]);
    $original = $plugin->getConfiguration()['hpc'];
    $settings = $plugin->getBlockConfig();
    $this->assertTrue((bool) $settings['donation']['enabled']);
    foreach (['title', 'description', 'url', 'button_label'] as $key) {
      $this->assertNotSame('', trim($settings['donation'][$key]));
    }
    $this->assertSame([], $settings['donation']['source_items']);
    $build = $plugin->buildContent();
    $this->assertNotNull($build['#donation']);
    $this->assertSame($settings['donation']['title'], $build['#donation']['title']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
  }

  /**
   * Tests explicit disabled or blank panels override legacy and default values.
   */
  public function testExplicitDonationSettingsSurviveEitherSubformSave(): void {
    foreach (['links', 'donation'] as $subform) {
      foreach ([
        ['enabled' => FALSE] + $this->createDonationValues(),
        ['enabled' => TRUE, 'title' => '', 'description' => '', 'url' => '', 'button_label' => ''],
      ] as $donation) {
        $plugin = $this->createLegacyBlock($donation);
        $effective = $plugin->getBlockConfig();
        foreach ($donation as $key => $value) {
          $this->assertSame($value, $effective['donation'][$key]);
        }
        $this->assertCount(1, $effective['donation']['source_items']);
        $this->assertNull($plugin->buildContent()['#donation']);
        $state = new FormState();
        $this->buildEditorialForm($plugin, $state);
        if ($subform === 'donation') {
          $this->switchSubform($plugin, $state, $subform);
        }
        $this->submitEditorialForm($plugin, $state, $subform === 'donation' ? ['donation' => $donation] : []);
        $this->assertSame([], $state->getErrors());
        $saved = $plugin->getConfiguration()['hpc']['donation'];
        $saved['enabled'] = (bool) $saved['enabled'];
        foreach ($donation as $key => $value) {
          $this->assertSame($value, $saved[$key]);
        }
        $this->assertSame($effective['donation']['source_items'], $saved['source_items']);
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
      'donation' => ['enabled' => FALSE],
    ]);
    $original = $plugin->getConfiguration()['hpc'];
    $this->assertSame([], $plugin->getBlockConfig()['links']['items']);
    $this->assertNull($plugin->buildContent()['#highlighted']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $this->assertArrayHasKey('donation', $form['actions']['subforms']);
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
    $this->assertSame($original['donation'], $configuration['donation']);
    $plugin->postSave($owner, $plugin->getUuid());
    foreach (array_merge($original['links']['items'], $original['donation']['source_items']) as $item) {
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
    $this->switchSubform($plugin, $state, 'donation');
    $donation = $this->createDonationValues();
    $donation['enabled'] = FALSE;
    $donation['title'] = 'Donation edited before saving order';
    $form = $this->switchSubform($plugin, $state, 'links', ['donation' => $donation]);

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

    $form = $this->switchSubform($plugin, $state, 'donation');
    $this->assertSame($donation['title'], $form['settings']['container']['title']['#value']);
    $this->assertFalse((bool) $form['settings']['container']['enabled']['#value']);
    $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    $this->submitEditorialForm($plugin, $state, ['donation' => $donation]);
    $this->assertSame([], $state->getErrors());
    $saved = $plugin->getConfiguration()['hpc'];
    $this->assertSame(4, $saved['links']['display_end_weight']);
    $this->assertSame(array_values($pending), $saved['links']['items']);
    $this->assertSame($donation['title'], $saved['donation']['title']);
    $this->assertFalse((bool) $saved['donation']['enabled']);
    $this->assertSame($original['donation']['source_items'], $saved['donation']['source_items']);
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
    $this->switchSubform($plugin, $state, 'donation', $this->createReorderedInput($items));
    $donation = $this->createDonationValues();
    $donation['title'] = 'An updated donation title';
    $donation['url'] = ' https://crisisrelief.un.org/en/donate ';
    $this->submitEditorialForm($plugin, $state, ['donation' => $donation]);

    $this->assertSame([], $state->getErrors());
    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame(4, $configuration['links']['display_end_weight']);
    $this->assertSame([5, 0, 1, 2, 3, 4], array_values(array_column($configuration['links']['items'], 'id')));
    $by_id = array_column($configuration['links']['items'], NULL, 'id');
    $this->assertSame('An unsaved edited news title', $by_id[0]['config']['label']);
    $this->assertSame('An updated donation title', $configuration['donation']['title']);
    $this->assertSame('https://crisisrelief.un.org/en/donate', $configuration['donation']['url']);
    $this->assertCount(1, $configuration['donation']['source_items']);
  }

  /**
   * Tests incomplete news remains editable and can be saved from either tab.
   */
  public function testIncompleteNewsCanSaveFromEitherSubform(): void {
    foreach (['links', 'donation'] as $subform) {
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
        if ($subform === 'donation') {
          $this->switchSubform($plugin, $state, $subform);
        }
        $this->submitEditorialForm($plugin, $state, $subform === 'donation' ? ['donation' => $this->createDonationValues()] : []);
        $this->assertSame([], $state->getErrors());
        $this->assertNewsRecordsUnchanged($configuration['hpc']['links']['items'], $plugin->getConfiguration()['hpc']['links']['items']);
        $donation = $plugin->getConfiguration()['hpc']['donation'];
        $donation['enabled'] = (bool) $donation['enabled'];
        $this->assertSame($configuration['hpc']['donation'], $donation);
      }
    }
  }

  /**
   * Tests incomplete donation settings can be saved from either tab.
   */
  public function testIncompleteDonationCanSaveFromEitherSubform(): void {
    foreach (['links', 'donation'] as $subform) {
      $plugin = $this->createEditorialBlock();
      $configuration = $plugin->getConfiguration();
      $configuration['hpc']['donation']['url'] = 'javascript:alert(1)';
      $configuration['hpc']['donation']['title'] = '';
      $plugin->setConfiguration($configuration);
      $state = new FormState();
      $state->clearErrors();
      $this->buildEditorialForm($plugin, $state);
      if ($subform === 'donation') {
        $this->switchSubform($plugin, $state, $subform);
      }
      $values = $subform === 'donation' ? ['donation' => $configuration['hpc']['donation']] : [];
      $this->submitEditorialForm($plugin, $state, $values);

      $this->assertSame([], $state->getErrors());
      $this->assertNewsRecordsUnchanged($configuration['hpc']['links']['items'], $plugin->getConfiguration()['hpc']['links']['items']);
      $donation = $plugin->getConfiguration()['hpc']['donation'];
      $donation['enabled'] = (bool) $donation['enabled'];
      $this->assertSame($configuration['hpc']['donation'], $donation);
    }
  }

  /**
   * Tests field-specific donation feedback follows pending nonblocking edits.
   */
  public function testDonationFeedbackFollowsPendingEdits(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $form = $this->switchSubform($plugin, $state, 'donation');
    $controls = $form['settings']['container'];
    $this->assertArrayNotHasKey('availability', $controls);
    $caption = $controls['caption'];
    $markup = (string) $this->container->get('renderer')->renderInIsolation($caption);
    $this->assertStringContainsString('link-carousel-editor__caption', $markup);
    $this->assertStringContainsString('<strong>Donation panel</strong>', $markup);
    $this->assertStringContainsString('Highlighted fields need attention', $markup);
    $this->assertStringContainsString('save incomplete settings', $markup);
    $this->assertContains('ghi_blocks/link_carousel.editor', $form['settings']['container']['#attached']['library']);
    $complete = $this->createDonationValues();
    $messages = [
      'title' => 'Add a title',
      'description' => 'Add a description',
      'url' => 'Enter a valid URL',
      'button_label' => 'Add a button label',
    ];
    $class = 'link-carousel-editor__field--unavailable';
    foreach ($messages as $invalid_field => $message) {
      $incomplete = $complete;
      $incomplete['enabled'] = FALSE;
      $incomplete[$invalid_field] = $invalid_field === 'url' ? 'javascript:alert(1)' : '';
      $this->switchSubform($plugin, $state, 'links', ['donation' => $incomplete]);
      $form = $this->switchSubform($plugin, $state, 'donation');
      $controls = $form['settings']['container'];
      foreach (array_keys($messages) as $field) {
        $highlighted = in_array($class, $controls[$field]['#wrapper_attributes']['class'] ?? [], TRUE);
        $this->assertSame($field === $invalid_field, $highlighted);
        $this->assertEmpty($controls[$field]['#required'] ?? FALSE);
      }
      $field_markup = (string) $this->container->get('renderer')->renderInIsolation($controls[$invalid_field]);
      $this->assertStringContainsString('Not available:', $field_markup);
      $this->assertStringContainsString($message, $field_markup);
      $this->assertStringContainsString('aria-describedby=', $field_markup);
      $this->assertNotContains($class, $controls['enabled']['#wrapper_attributes']['class'] ?? []);
      $this->assertSame([], $state->getErrors());
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    }

    $this->switchSubform($plugin, $state, 'links', ['donation' => $complete]);
    $form = $this->switchSubform($plugin, $state, 'donation');
    foreach (array_keys($messages) as $field) {
      $this->assertNotContains($class, $form['settings']['container'][$field]['#wrapper_attributes']['class'] ?? []);
      $description = (string) ($form['settings']['container'][$field]['#description'] ?? '');
      $this->assertStringNotContainsString('Not available:', $description);
    }
    $this->assertStringContainsString('HTTP or HTTPS', (string) $form['settings']['container']['url']['#description']);
    $this->submitEditorialForm($plugin, $state, ['donation' => $complete]);
    $this->assertSame([], $state->getErrors());
    $this->assertNotNull($plugin->buildContent()['#donation']);
  }

  /**
   * Tests unavailable local targets highlight only the donation URL field.
   */
  public function testDonationDestinationHighlightsRemainNonblocking(): void {
    $draft = Node::create(['type' => 'page', 'title' => 'Future donation campaign', 'status' => FALSE]);
    $draft->save();
    $plugin = $this->createEditorialBlock();
    $state = new FormState();
    $this->buildEditorialForm($plugin, $state);
    $this->switchSubform($plugin, $state, 'donation');
    foreach ([
      'entity:node/' . $draft->id() => 'unpublished content',
      'internal:/admin' => 'not currently accessible to anonymous visitors',
    ] as $url => $message) {
      $donation = $this->createDonationValues();
      $donation['url'] = $url;
      $this->switchSubform($plugin, $state, 'links', ['donation' => $donation]);
      $form = $this->switchSubform($plugin, $state, 'donation');
      $controls = $form['settings']['container'];
      foreach (['title', 'description', 'url', 'button_label', 'enabled'] as $field) {
        $highlighted = in_array('link-carousel-editor__field--unavailable', $controls[$field]['#wrapper_attributes']['class'] ?? [], TRUE);
        $this->assertSame($field === 'url', $highlighted);
      }
      $this->assertStringContainsString($message, (string) $controls['url']['#description']);
      $this->assertSame([], $state->getErrors());
    }
    $this->submitEditorialForm($plugin, $state, ['donation' => $donation]);
    $this->assertSame([], $state->getErrors());
    $this->assertSame($url, $plugin->getConfiguration()['hpc']['donation']['url']);
    $this->assertNull($plugin->buildContent()['#donation']);
  }

  /**
   * Tests native field AJAX updates only feedback without submitting the block.
   */
  public function testDonationFieldAjaxPreservesInputsAndPendingSettings(): void {
    $plugin = $this->createEditorialBlock();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['donation']['title'] = '';
    $plugin->setConfiguration($configuration);
    $original = $plugin->getConfiguration();
    $state = new FormState();
    $form = $this->buildEditorialForm($plugin, $state);
    $pending_news = $original['hpc']['links']['items'];
    $pending_news[0]['config']['label'] = 'An unsaved news edit';
    ConfigurationContainer::setItems($form['settings']['container']['items'], $state, $pending_news);
    $form = $this->switchSubform($plugin, $state, 'donation');
    foreach (['title', 'description', 'url', 'button_label'] as $key) {
      $field = $form['settings']['container'][$key];
      $this->assertArrayNotHasKey('#ajax', $field);
      $this->assertSame($key, $field['#wrapper_attributes']['data-link-carousel-donation-field']);
      $this->assertStringContainsString('data-link-carousel-donation-feedback', (string) $field['#description']);
      $this->assertStringContainsString('aria-live="polite"', (string) $field['#description']);
    }
    $feedback = $form['settings']['container']['feedback'];
    $this->assertSame('hidden', $feedback['#type']);
    $this->assertSame('', $feedback['#default_value']);
    $this->assertSame($feedback['#id'], $feedback['#attributes']['id']);
    $markup = (string) $this->container->get('renderer')->renderInIsolation($feedback);
    $this->assertStringContainsString('id="' . $feedback['#id'] . '"', $markup);
    $this->assertSame(['link_carousel_donation_feedback'], $feedback['#parents']);
    $this->assertContains(['link_carousel_donation_feedback'], $state->getCleanValueKeys());
    $this->assertSame('linkCarouselDonationFeedback', $feedback['#ajax']['event']);
    $this->assertSame([$plugin, 'updateDonationFeedback'], $feedback['#ajax']['callback']);
    $this->assertSame(['type' => 'none'], $feedback['#ajax']['progress']);
    $this->assertTrue($feedback['#ajax']['disable-refocus']);
    $this->assertFalse($feedback['#ajax']['refocus-blur']);
    $values = $this->createDonationValues();
    foreach ([
      ['title', '', TRUE],
      ['title', 'A completed donation title', FALSE],
      ['url', 'javascript:alert(1)', TRUE],
      ['url', 'https://crisisrelief.un.org/donate', FALSE],
    ] as [$key, $value, $unavailable]) {
      $values[$key] = $value;
      $form = $this->submitDonationFieldChange($plugin, $state, $values, $key);
      $this->assertFalse($state->isProgrammed());
      $this->assertFalse($state->isSubmitted());
      $this->assertFalse($state->isExecuted());
      $this->assertSame([], $state->getErrors());
      $this->assertSame(['link_carousel_donation_feedback'], $state->getTriggeringElement()['#parents']);
      $this->assertSame($key, $state->getTriggeringElement()['#value']);
      $this->assertArrayNotHasKey('link_carousel_donation_feedback', $state->cleanValues()->getValues());
      $this->assertSame($value, $form['settings']['container'][$key]['#value']);
      $submitted_values = $state->getValues();
      $callback = $state->getTriggeringElement()['#ajax']['callback'];
      $commands = $callback($form, $state)->getCommands();
      $selector = '#' . $form['#id'] . ' [data-link-carousel-donation-field="' . $key . '"]';
      $this->assertCount(2, $commands);
      $this->assertSame([
        'command' => 'invoke',
        'selector' => $selector,
        'method' => 'toggleClass',
        'args' => ['link-carousel-editor__field--unavailable', $unavailable],
      ], $commands[0]);
      $this->assertSame('insert', $commands[1]['command']);
      $this->assertSame('html', $commands[1]['method']);
      $this->assertSame($selector . ' [data-link-carousel-donation-feedback]', $commands[1]['selector']);
      $this->assertSame($unavailable, str_contains($commands[1]['data'], 'Not available:'));
      $this->assertStringNotContainsString('HTTP or HTTPS', $commands[1]['data']);
      $this->assertSame($submitted_values, $state->getValues());
      $this->assertSame($original, $plugin->getConfiguration());
      $this->assertNewsRecordsUnchanged($pending_news, $plugin->getBlockConfig()['links']['items']);
      $this->assertSame($original['hpc']['donation']['source_items'], $state->getValue(['donation', 'source_items']));
    }
    $this->assertStringContainsString('HTTP or HTTPS', (string) $form['settings']['container']['url']['#description']);
    $form = $this->submitDonationFieldChange($plugin, $state, $values, 'unrecognized_field');
    $callback = $state->getTriggeringElement()['#ajax']['callback'];
    $this->assertSame([], $callback($form, $state)->getCommands());
    $this->assertArrayNotHasKey('link_carousel_donation_feedback', $state->cleanValues()->getValues());
    $this->assertSame($original, $plugin->getConfiguration());
    $state->set('current_subform', 'links');
    $trigger = $state->getTriggeringElement();
    $trigger['#value'] = 'title';
    $state->setTriggeringElement($trigger);
    $this->assertSame([], $callback($form, $state)->getCommands());
    $state->set('current_subform', 'donation');
    $this->submitEditorialForm($plugin, $state, ['donation' => $values, 'link_carousel_donation_feedback' => 'title']);
    $this->assertSame([], $state->getErrors());
    $this->assertSame($values['title'], $plugin->getConfiguration()['hpc']['donation']['title']);
    $this->assertSame($values['url'], $plugin->getConfiguration()['hpc']['donation']['url']);
    $this->assertArrayNotHasKey('link_carousel_donation_feedback', $state->getValues());
    $this->assertArrayNotHasKey('link_carousel_donation_feedback', $plugin->getConfiguration()['hpc']);
  }

  /**
   * Tests validation prefers current input over a cached feedback form state.
   */
  public function testValidationUsesPassedStateInsteadOfCachedFeedbackState(): void {
    $plugin = $this->createEditorialBlock();
    $original = $plugin->getConfiguration()['hpc'];
    foreach ([
      ['button_label' => 'A freshly submitted donation label'],
      ['enabled' => FALSE, 'title' => '', 'button_label' => '', 'url' => 'javascript:alert(1)'],
    ] as $changes) {
      $stale = new FormState();
      $stale->setValues($original);
      $stale->setValue(['donation', 'button_label'], 'An older feedback label');
      $plugin->setFormState($stale);
      $this->assertSame('An older feedback label', $plugin->getBlockConfig()['donation']['button_label']);
      $stale_values = $stale->getValues();
      $donation = $changes + $original['donation'];
      $news = $original['links'];
      $news['items'][0]['config']['label'] = 'A freshly submitted news title';
      $news['items'][0]['config']['value']['url'] = 'An unfinished news destination';
      $current = new FormState();
      $current->setValues(['links' => $news, 'donation' => $donation]);
      $current->setTriggeringElement(['#parents' => ['actions', 'submit']]);

      $plugin->blockValidate([], $current);

      $this->assertSame([], $current->getErrors());
      foreach (['links' => $news, 'donation' => $donation] as $key => $expected) {
        $this->assertSame($expected, $current->getValue($key));
        $this->assertSame($expected, $current->getTemporaryValue($key));
        $this->assertSame($expected, $current->get(['storage', $key]));
      }
      $this->assertSame($stale_values, $stale->getValues());
      $this->assertSame($original, $plugin->getConfiguration()['hpc']);
    }
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
    $this->switchSubform($plugin, $state, 'donation', $this->createReorderedInput($configuration['hpc']['links']['items']));
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
        $this->switchSubform($plugin, $state, 'donation');
        $this->submitEditorialForm($plugin, $state, ['donation' => $this->createDonationValues()]);
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
      $this->switchSubform($plugin, $state, 'donation');
      $this->submitEditorialForm($plugin, $state, ['donation' => $this->createDonationValues()]);
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

    $this->switchSubform($plugin, $state, 'donation');
    $this->submitEditorialForm($plugin, $state, ['donation' => $this->createDonationValues()]);
    $this->assertSame([], $state->getErrors());
    $saved = array_column($plugin->getConfiguration()['hpc']['links']['items'], NULL, 'id');
    $this->assertCount(7, $saved);
    $this->assertSame('entity:node/' . $draft->id(), $saved[6]['config']['value']['url']);
  }

  /**
   * Tests incomplete and unsupported donation URLs are retained for editing.
   */
  public function testIncompleteDonationFieldsAreRetained(): void {
    foreach (['not-a-url', 'javascript:alert(1)', 'ftp://example.com/donate', 'internal:/missing-donation-page'] as $url) {
      $plugin = $this->createEditorialBlock();
      $state = new FormState();
      $state->clearErrors();
      $this->buildEditorialForm($plugin, $state);
      $this->switchSubform($plugin, $state, 'donation');
      $donation = $this->createDonationValues();
      $donation['url'] = $url;
      $this->submitEditorialForm($plugin, $state, ['donation' => $donation]);
      $this->assertSame([], $state->getErrors());
      $this->assertSame($url, $plugin->getConfiguration()['hpc']['donation']['url']);
    }
    $plugin = $this->createEditorialBlock();
    $state = new FormState();
    $state->clearErrors();
    $this->buildEditorialForm($plugin, $state);
    $this->switchSubform($plugin, $state, 'donation');
    $this->submitEditorialForm($plugin, $state, [
      'donation' => [
        'enabled' => TRUE,
        'title' => '',
        'description' => '',
        'url' => '',
        'button_label' => '',
      ],
    ]);
    $this->assertSame([], $state->getErrors());
    foreach (['title', 'description', 'url', 'button_label'] as $key) {
      $this->assertSame('', $plugin->getConfiguration()['hpc']['donation'][$key]);
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
    $form = $this->switchSubform($plugin, $state, 'donation');
    $donation = $this->createDonationValues();
    $donation['title'] = 'Donation edited before preview';
    $state->setValue('donation', $donation);
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
    $this->assertSame('Donation edited before preview', $plugin->getConfiguration()['hpc']['donation']['title']);
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
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'links' => ['items' => $items, 'display_end_weight' => $display_end_weight],
      'donation' => $this->createDonationValues() + ['source_items' => [$source]],
    ]);
    $plugin->setConfigurationValue('uuid', $this->container->get('uuid')->generate());
    return $plugin;
  }

  /**
   * Creates untouched legacy storage with the old donation slide ranked first.
   */
  private function createLegacyBlock(?array $donation = NULL): LinkCarousel {
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
    if ($donation !== NULL) {
      $settings['donation'] = $donation;
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
   * Creates editable donation values without the retained source records.
   */
  private function createDonationValues(): array {
    return [
      'enabled' => TRUE,
      'title' => 'Donate to relief organizations',
      'description' => 'Support people affected by emergencies.',
      'url' => 'https://crisisrelief.un.org/en/donate',
      'button_label' => 'Donate now',
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
  private function submitDonationFieldChange(LinkCarousel $plugin, FormState &$state, array $values, string $key): array {
    $previous_form = $state->getCompleteForm();
    $feedback = $previous_form['settings']['container']['feedback'];
    $next_state = new FormState();
    $next_state->setFormState($state->getCacheableArray());
    $next_state->setTemporary($state->getTemporary());
    $next_state->setProgrammed(FALSE)->setRequestMethod('POST')->disableRedirect();
    $input = [
      'form_id' => 'link_carousel_editorial_test',
      '_triggering_element_name' => $feedback['#name'],
      'link_carousel_donation_feedback' => $key,
      'donation' => $values,
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
