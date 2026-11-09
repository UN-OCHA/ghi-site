<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\ghi_blocks\Helpers\LinkCarouselLink;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the static highlighted and featured link carousel presentation.
 */
#[Group('ghi_blocks')]
class LinkCarouselFeatureBlockTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'image',
    'breakpoint',
    'responsive_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installConfig(['system', 'image']);
  }

  /**
   * Tests every supported featured count following the main news row.
   */
  #[DataProvider('featuredCountProvider')]
  public function testSupportedFeaturedCounts(int $count): void {
    $items = [$this->createItem('hero')];
    $ids = [];
    for ($index = 0; $index < $count; $index++) {
      $id = 'featured-' . $index;
      $items[] = $this->createItem($id);
      $ids[] = $id;
    }
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertSame('link_carousel_feature', $build['#theme']);
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertCount($count, $build['#featured']);
    $this->assertSame(array_map(static fn(string $id): string => 'Item ' . $id, $ids), array_column($build['#featured'], 'title'));
  }

  /**
   * Supplies all supported featured counts.
   */
  public static function featuredCountProvider(): array {
    return array_map(static fn(int $count): array => [$count], range(0, 4));
  }

  /**
   * Tests a missing boundary retains the first five valid rows without writes.
   */
  public function testMissingDisplayBoundaryDefaultsWithoutMutation(): void {
    $items = [];
    foreach (range(0, 5) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }
    $plugin = $this->createFeatureBlock($items);
    $plugin->setConfigurationValue('hpc', ['links' => ['items' => $items]]);
    $original = $plugin->getConfiguration();

    $this->assertNull($plugin->getBlockConfig()['links']['display_end_weight']);
    $this->assertCount(4, $plugin->buildContent()['#featured']);
    $this->assertSame($original, $plugin->getConfiguration());
    $this->assertArrayNotHasKey('display_end_weight', $plugin->getConfiguration()['hpc']['links']);
  }

  /**
   * Tests a physical cutoff never backfills invalid rows from below it.
   */
  public function testDisplayBoundaryRetainsRowsWithoutAvailabilityBackfill(): void {
    $items = [];
    foreach (range(0, 6) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }
    $plugin = $this->createFeatureBlock($items);
    foreach ([1, 2, 3, 4, 5, 99] as $boundary) {
      $plugin->setConfigurationValue('hpc', ['links' => ['items' => $items, 'display_end_weight' => $boundary]]);
      $original = $plugin->getConfiguration();

      $build = $plugin->buildContent();

      $expected = min(4, $boundary - 1);
      $this->assertSame($boundary, $plugin->getBlockConfig()['links']['display_end_weight']);
      $this->assertSame('Item 0', $build['#highlighted']['title']);
      $this->assertCount($expected, $build['#featured']);
      $titles = array_map(static fn(int $index): string => 'Item ' . $index, range(1, 4));
      $this->assertSame(array_slice($titles, 0, $expected), array_column($build['#featured'], 'title'));
      $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
      $this->assertCount(7, $this->callPrivateMethod($plugin, 'getFiles'));
      $this->assertNotContains('file:' . $items[6]['config']['value']['image'][0], $build['#cache']['tags']);
      $this->assertSame($original, $plugin->getConfiguration());
    }

    $items[1]['config']['value']['url'] = 'unfinished destination';
    $plugin->setConfigurationValue('hpc', ['links' => ['items' => $items, 'display_end_weight' => 3]]);
    $this->assertSame(['Item 2'], array_column($plugin->buildContent()['#featured'], 'title'));
    $items[0]['config']['value']['url'] = 'unfinished destination';
    $plugin->setConfigurationValue('hpc', ['links' => ['items' => $items, 'display_end_weight' => 3]]);
    $build = $plugin->buildContent();
    $this->assertSame('Item 2', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $items[1]['config']['value']['url'] = 'https://example.com/repaired';
    $plugin->setConfigurationValue('hpc', ['links' => ['items' => $items, 'display_end_weight' => 3]]);
    $build = $plugin->buildContent();
    $this->assertSame('Item 1', $build['#highlighted']['title']);
    $this->assertSame(['Item 2'], array_column($build['#featured'], 'title'));
    $this->assertSame(3, $plugin->getBlockConfig()['links']['display_end_weight']);
  }

  /**
   * Tests unmodified stored items without requiring a configuration migration.
   */
  public function testOriginalItemConfigurationUsesNewPresentationWithoutMutation(): void {
    $hero = $this->createItem('hero', 0);
    $featured = $this->createItem('featured', 1);
    $legacy_donation = $this->createItem('legacy-donation', -100, [
      'url' => 'https://crisisrelief.un.org/donate',
      'description' => 'An original donation description',
      'button_label' => 'Original donation label',
    ]);
    $items = ['donation' => $legacy_donation, 'featured' => $featured, 'hero' => $hero];
    $plugin = $this->createBlockPlugin('generic_link_carousel', ['items' => $items]);
    $raw_configuration = $plugin->getConfiguration();

    $effective = $plugin->getBlockConfig();
    $this->assertCount(2, $effective['links']['items']);
    $this->assertSame($legacy_donation, $effective['cta']['source_items']['donation']);
    $build = $plugin->buildContent();
    $this->assertSame('link_carousel_feature', $build['#theme']);
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertSame(['Item featured'], array_column($build['#featured'], 'title'));
    $this->assertNotNull($build['#call_to_action']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
    $this->assertSame($items, $plugin->getConfiguration()['hpc']['items']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());

    $files = $this->callPrivateMethod($plugin, 'getFiles');
    $this->assertCount(3, $files);
    $this->assertArrayHasKey($legacy_donation['config']['value']['image'][0], $files);
    $this->assertNotContains('file:' . $legacy_donation['config']['value']['image'][0], $build['#cache']['tags']);
  }

  /**
   * Tests an empty historical or new instance shows the default call to action.
   */
  public function testEmptyUnconfiguredInstanceDisplaysDefaultCallToAction(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', []);
    $raw_configuration = $plugin->getConfiguration();

    $build = $plugin->buildContent();
    $this->assertSame('link_carousel_feature', $build['#theme']);
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame('Donate to relief organizations', $build['#call_to_action']['title']);
    $this->assertSame(1, $plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertCount(1, $plugin->getBlockConfig()['cta']['items']);
    $this->assertNull($plugin->provideImageUri());
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests deliberately empty CTA records are not replaced with default copy.
   */
  public function testExplicitEmptyCallToActionItemsStayEmpty(): void {
    $plugin = $this->createFeatureBlock([], ['cta' => ['items' => [], 'active_item_id' => NULL]]);
    $raw_configuration = $plugin->getConfiguration();

    $effective = $plugin->getBlockConfig();
    $this->assertSame([], $effective['cta']['items']);
    $this->assertNull($effective['cta']['active_item_id']);
    $this->assertNull($plugin->buildContent()['#call_to_action']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests an archived donation slide cannot reactivate an inactive CTA.
   */
  public function testInactiveCallToActionIsNotOverriddenByOriginalSlide(): void {
    $items = [
      $this->createItem('donation', -100, ['url' => 'https://crisisrelief.un.org/en/donate']),
      $this->createItem('news', 0),
    ];
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'items' => $items,
      'cta' => ['active_item_id' => NULL],
    ]);
    $raw_configuration = $plugin->getConfiguration();

    $build = $plugin->buildContent();
    $this->assertNull($plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertNull($build['#call_to_action']);
    $this->assertSame('Item news', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $this->assertCount(2, $this->callPrivateMethod($plugin, 'getFiles'));
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests selection is independent from item weights and preserves other CTAs.
   */
  public function testSelectedCallToActionCanChangeWithoutRemovingOtherItems(): void {
    $cta = [
      'items' => [
        $this->createCallToActionItem(0, 20, [], 'First action'),
        $this->createCallToActionItem('alternate', 0, ['url' => 'https://example.com/action'], 'Second action'),
      ],
      'active_item_id' => 0,
      'source_items' => [],
    ];
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);
    $raw_configuration = $plugin->getConfiguration();

    $this->assertSame('First action', $plugin->buildContent()['#call_to_action']['title']);
    $this->assertSame(0, $plugin->getBlockConfig()['cta']['active_item_id']);
    $this->assertSame($cta['items'], $plugin->getBlockConfig()['cta']['items']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());

    $cta['active_item_id'] = 'alternate';
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);
    $raw_configuration = $plugin->getConfiguration();
    $this->assertSame('Second action', $plugin->buildContent()['#call_to_action']['title']);
    $this->assertCount(2, $plugin->getBlockConfig()['cta']['items']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests unavailable selected CTAs never fall back to another saved item.
   */
  #[DataProvider('inactiveCallToActionProvider')]
  public function testMissingOrInvalidActiveCallToActionDoesNotFallBack(int|string|null $active_id): void {
    $cta = [
      'items' => [
        $this->createCallToActionItem(1),
        $this->createCallToActionItem('invalid', 1, ['url' => 'unfinished destination']),
      ],
      'active_item_id' => $active_id,
      'source_items' => [],
    ];
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);

    $this->assertNull($plugin->buildContent()['#call_to_action']);
    $this->assertSame($cta, $plugin->getBlockConfig()['cta']);
  }

  /**
   * Supplies a deliberately inactive, missing, or unavailable selection.
   */
  public static function inactiveCallToActionProvider(): array {
    return [
      'no active item' => [NULL],
      'missing active item' => ['missing'],
      'unavailable active item' => ['invalid'],
    ];
  }

  /**
   * Tests that weights determine news order and the social image.
   */
  public function testWeightsDetermineNewsOrderAndSocialImage(): void {
    $first_file = $this->createImageFile();
    $second_file = $this->createImageFile();
    $items = [
      $this->createItem('first', 30, ['image' => [$first_file->id()]]),
      $this->createItem('second', 10, ['image' => [$second_file->id()]]),
      $this->createItem('third', 20),
    ];
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertSame('Item second', $build['#highlighted']['title']);
    $this->assertSame(['Item third', 'Item first'], array_column($build['#featured'], 'title'));
    $this->assertSame($second_file->getFileUri(), $build['#highlighted']['image']['#uri']);
    $this->assertSame($second_file->getFileUri(), $plugin->provideImageUri());

    $items[0]['weight'] = -100;
    $plugin = $this->createFeatureBlock($items);
    $build = $plugin->buildContent();
    $this->assertSame('Item first', $build['#highlighted']['title']);
    $this->assertSame(['Item second', 'Item third'], array_column($build['#featured'], 'title'));
    $this->assertSame($first_file->getFileUri(), $build['#highlighted']['image']['#uri']);
    $this->assertSame($first_file->getFileUri(), $plugin->provideImageUri());
  }

  /**
   * Tests that invalid first rows are skipped before choosing the main news.
   */
  #[DataProvider('invalidNewsProvider')]
  public function testInvalidFirstRowIsSkippedWhenChoosingMainNews(array $values): void {
    $plugin = $this->createFeatureBlock([
      $this->createItem('hero', 0, $values),
      $this->createItem('featured', 1),
    ]);

    $build = $plugin->buildContent();
    $this->assertSame('Item featured', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
    if (isset($values['image'])) {
      $this->assertContains('file_list', $build['#cache']['tags']);
    }
  }

  /**
   * Supplies missing images and missing or malformed destinations.
   */
  public static function invalidNewsProvider(): array {
    return [
      'missing image' => [['image' => []]],
      'deleted image' => [['image' => ['99999999']]],
      'missing destination' => [['url' => '']],
      'malformed destination' => [['url' => 'not a URI']],
    ];
  }

  /**
   * Tests that retained rows without a title do not become the main news.
   */
  public function testMissingTitleIsSkippedBeforeChoosingMainNews(): void {
    $untitled = $this->createItem('untitled', 0);
    $untitled['config']['label'] = '   ';
    $plugin = $this->createFeatureBlock([$untitled, $this->createItem('available', 1)]);

    $build = $plugin->buildContent();
    $this->assertSame('Item available', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
  }

  /**
   * Tests that the first five valid weighted news rows are publicly active.
   */
  public function testFirstFiveValidWeightedRowsAreActive(): void {
    $items = [$this->createItem('hero')];
    foreach (range(1, 6) as $index) {
      $items[] = $this->createItem((string) $index, 100 - $index);
    }
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertSame(['Item 6', 'Item 5', 'Item 4', 'Item 3'], array_column($build['#featured'], 'title'));
  }

  /**
   * Tests valid rows fill gaps when no explicit display boundary is stored.
   */
  #[DataProvider('invalidNewsProvider')]
  public function testInvalidRowsAreSkippedBeforeChoosingFeaturedNews(array $values): void {
    $items = [$this->createItem('hero', 0), $this->createItem('invalid', 1, $values)];
    foreach (range(2, 6) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertSame(['Item 2', 'Item 3', 'Item 4', 'Item 5'], array_column($build['#featured'], 'title'));
  }

  /**
   * Tests that access-denied rows do not occupy a featured position.
   */
  public function testDeniedRowsAreSkippedBeforeChoosingFeaturedNews(): void {
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::forbidden()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);
    $items = [$this->createItem('hero', 0), $this->createItem('denied', 1, ['url' => 'internal:/admin'])];
    foreach (range(2, 6) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }

    $build = $this->createFeatureBlock($items)->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertSame(['Item 2', 'Item 3', 'Item 4', 'Item 5'], array_column($build['#featured'], 'title'));
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
  }

  /**
   * Tests the renderer reuses the availability check's resolved destination.
   */
  public function testAvailableNewsResolvesPublicAccessOnce(): void {
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects($this->once())->method('checkNamedRoute')->willReturn(AccessResult::allowed()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);
    $this->assertSame('internal:/admin', LinkCarouselLink::normalize('internal:/admin'));
    $plugin = $this->createFeatureBlock([$this->createItem('hero', 0, ['url' => 'internal:/admin'])]);

    $build = $plugin->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
  }

  /**
   * Tests real node access conversion without replacing raw URL parameters.
   */
  #[DataProvider('nodeDestinationProvider')]
  public function testNodeLinksKeepRawRouteParametersAndCacheDependencies(string $scheme): void {
    $this->enableModules(['filter', 'text', 'node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'user']);
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Published article',
      'status' => TRUE,
      'uid' => 0,
    ]);
    $node->save();
    $uri = $scheme === 'entity' ? 'entity:node/' . $node->id() : 'internal:/node/' . $node->id();
    $cache = new CacheableMetadata();
    $resolved = LinkCarouselLink::resolve($uri, $cache);
    $this->assertSame([], $resolved['issues']);
    $this->assertSame((string) $node->id(), $resolved['url']->getRouteParameters()['node']);
    $this->assertContains('node:' . $node->id(), $cache->getCacheTags());
    $this->assertContains('route_match', $cache->getCacheTags());
    $this->assertContains('user.permissions', $cache->getCacheContexts());
    $plugin = $this->createFeatureBlock([$this->createItem('hero', 0, ['url' => $uri])]);

    $build = $plugin->buildContent();
    $this->assertNotNull($build['#highlighted']);
    $this->assertContains('node:' . $node->id(), $build['#cache']['tags']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
    $link = $build['#highlighted']['button'];
    $this->assertSame((string) $node->id(), $link['#url']->getRouteParameters()['node']);
    $html = (string) $this->container->get('renderer')->renderRoot($link);
    $this->assertStringContainsString('href="/node/' . $node->id() . '"', $html);
    $this->assertSame((string) $node->id(), $link['#url']->getRouteParameters()['node']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
  }

  /**
   * Supplies both supported forms of stored local node destinations.
   */
  public static function nodeDestinationProvider(): array {
    return [
      'entity destination' => ['entity'],
      'internal destination' => ['internal'],
    ];
  }

  /**
   * Tests that privileged previews still use anonymous publication access.
   */
  public function testUnpublishedNewsIsSkippedEvenInEditorPreview(): void {
    $this->enableModules(['filter', 'text', 'node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'user']);
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::create([
      'id' => 'editor',
      'label' => 'Editor',
      'permissions' => ['access content', 'bypass node access'],
    ])->save();
    $editor = User::create(['name' => 'Editor', 'status' => TRUE, 'roles' => ['editor']]);
    $editor->save();
    $this->container->get('current_user')->setAccount($editor);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished article',
      'status' => FALSE,
      'uid' => 0,
    ]);
    $node->save();
    $this->assertTrue($node->access('view', $editor));
    $items = [$this->createItem('unpublished', 0, ['url' => 'entity:node/' . $node->id()])];
    foreach (range(1, 6) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }
    $plugin = $this->createFeatureBlock($items);
    $configuration = $plugin->getConfiguration();
    $configuration['is_preview'] = TRUE;
    $plugin->setConfiguration($configuration);

    $build = $plugin->buildContent();
    $this->assertSame('Item 1', $build['#highlighted']['title']);
    $this->assertSame(['Item 2', 'Item 3', 'Item 4', 'Item 5'], array_column($build['#featured'], 'title'));
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
    $this->assertContains('node:' . $node->id(), $build['#cache']['tags']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    // Draft destinations remain valid editorial input, but share the same
    // availability result for news and the selected call to action.
    $uri = 'entity:node/' . $node->id();
    $this->assertSame($uri, LinkCarouselLink::normalize($uri));
    $cache = new CacheableMetadata();
    $resolved = LinkCarouselLink::resolve($uri, $cache);
    $this->assertNull($resolved['url']);
    $this->assertSame(['This link points to unpublished content.'], array_map('strval', $resolved['issues']));
    $this->assertContains('node:' . $node->id(), $cache->getCacheTags());
    $cta = $this->createCallToActionConfiguration(['url' => $uri]);
    $cta_plugin = $this->createFeatureBlock([], ['cta' => $cta]);
    $cta_build = $cta_plugin->buildContent();
    $this->assertNull($cta_build['#call_to_action']);
    $this->assertContains('node:' . $node->id(), $cta_build['#cache']['tags']);
    $this->assertContains('user.permissions', $cta_build['#cache']['contexts']);
    $this->assertSame($cta, $cta_plugin->getBlockConfig()['cta']);
  }

  /**
   * Tests that invalid destinations do not occupy public news positions.
   */
  #[DataProvider('invalidDestinationProvider')]
  public function testInvalidDestinationsAreSkippedBeforeChoosingMainNews(string $uri): void {
    $items = [];
    foreach (range(0, 4) as $index) {
      $items[] = $this->createItem('invalid-' . $index, $index, ['url' => $uri]);
    }
    $items[] = $this->createItem('overflow', 5);
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertSame('Item overflow', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
  }

  /**
   * Supplies invalid internal destinations and excluded external schemes.
   */
  public static function invalidDestinationProvider(): array {
    return [
      'removed internal alias' => ['internal:/hpc-10626-removed-alias'],
      'JavaScript URL' => ['javascript:alert(1)'],
      'data URL' => ['data:text/html,<script>alert(1)</script>'],
      'FTP URL' => ['ftp://example.com/article'],
      'malformed HTTPS URL' => ['https://'],
      'malformed entity URI' => ['entity:node'],
    ];
  }

  /**
   * Tests that a call to action can be displayed without any news items.
   */
  public function testCallToActionDoesNotRequireNewsItems(): void {
    $plugin = $this->createFeatureBlock([], ['cta' => $this->createCallToActionConfiguration()]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame('Donate to relief organizations', $build['#call_to_action']['title']);
    $this->assertSame('https://crisisrelief.un.org/donate', $build['#call_to_action']['button']['#url']->toString());
    $attributes = $build['#call_to_action']['button']['#url']->getOption('attributes');
    $this->assertContains('cd-button', $attributes['class']);
    $this->assertContains('external', $attributes['class']);
    $this->assertSame('_blank', $attributes['target']);
    $this->assertSame('noopener noreferrer', $attributes['rel']);
    $this->assertNull($plugin->provideImageUri());
  }

  /**
   * Tests that an unresolved CTA destination can become available later.
   */
  public function testUnavailableCallToActionDestinationKeepsCacheDependencies(): void {
    $cta = $this->createCallToActionConfiguration(['url' => 'internal:/hpc-10626-future-action']);
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#call_to_action']);
    $this->assertContains('node_list', $build['#cache']['tags']);
    $this->assertContains('route_match', $build['#cache']['tags']);
    $this->assertSame($cta, $plugin->getBlockConfig()['cta']);
  }

  /**
   * Tests the selected CTA follows the destination's anonymous access rules.
   */
  public function testCallToActionTracksAnonymousAccess(): void {
    $cta = $this->createCallToActionConfiguration(['url' => 'internal:/admin']);
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::forbidden()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);

    $build = $plugin->buildContent();
    $this->assertNull($build['#call_to_action']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
    $this->assertContains('route_match', $build['#cache']['tags']);

    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::allowed()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);
    $build = $plugin->buildContent();
    $this->assertNotNull($build['#call_to_action']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
    $this->assertSame($cta, $plugin->getBlockConfig()['cta']);
  }

  /**
   * Tests incomplete saved CTA values remain unchanged and absent publicly.
   */
  #[DataProvider('incompleteCallToActionProvider')]
  public function testIncompleteCallToActionIsNotDisplayed(string $field): void {
    $cta = $this->createCallToActionConfiguration();
    if ($field === 'title') {
      $cta['items'][0]['config']['label'] = '';
    }
    else {
      $cta['items'][0]['config']['value'][$field] = '';
    }
    $plugin = $this->createFeatureBlock([$this->createItem('hero')], ['cta' => $cta]);
    $raw_configuration = $plugin->getConfiguration();

    $build = $plugin->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertNull($build['#call_to_action']);
    $this->assertSame($cta, $plugin->getBlockConfig()['cta']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
    $this->assertContains('route_match', $build['#cache']['tags']);
  }

  /**
   * Supplies each field required for a complete public call to action.
   */
  public static function incompleteCallToActionProvider(): array {
    return [
      'missing title' => ['title'],
      'missing description' => ['description'],
      'missing destination' => ['url'],
      'missing button label' => ['button_label'],
    ];
  }

  /**
   * Tests that archived donation rows never become main news or featured cards.
   */
  public function testDonationArchivesNeverBecomeNews(): void {
    $source_file = $this->createImageFile();
    $cta = $this->createCallToActionConfiguration();
    $cta['source_items'] = [
      $this->createItem('archived-donation', -100, [
        'image' => [$source_file->id()],
        'url' => 'https://crisisrelief.un.org/donate',
      ]),
    ];
    $plugin = $this->createFeatureBlock([], ['cta' => $cta]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertNotNull($build['#call_to_action']);
    $this->assertNotContains('file:' . $source_file->id(), $build['#cache']['tags']);
    $this->assertNull($plugin->provideImageUri());
  }

  /**
   * Tests shared files remain retained once across hidden and archived records.
   */
  public function testFileRetentionDeduplicatesNewsAndDonationImages(): void {
    $news_file = $this->createImageFile();
    $archived_file = $this->createImageFile();
    $items = [
      $this->createItem('hero', 0, ['image' => [$news_file->id()]]),
      $this->createItem('hidden', 2, ['image' => [$news_file->id()]]),
    ];
    $cta = $this->createCallToActionConfiguration();
    $cta['active_item_id'] = NULL;
    $cta['source_items'] = [
      $this->createItem('archived', 0, ['image' => [$archived_file->id()]]),
      $this->createItem('shared-archive', 1, ['image' => [$news_file->id()]]),
    ];
    $plugin = $this->createFeatureBlock($items, [
      'links' => ['items' => $items, 'display_end_weight' => 1],
      'cta' => $cta,
    ]);

    $files = $this->callPrivateMethod($plugin, 'getFiles');
    $this->assertSame([(int) $news_file->id(), (int) $archived_file->id()], array_keys($files));
    $this->assertSame($news_file->getFileUri(), $files[$news_file->id()]->getFileUri());
    $this->assertSame($archived_file->getFileUri(), $files[$archived_file->id()]->getFileUri());
    $this->assertSame([], $this->callPrivateMethod($this->createFeatureBlock(), 'getFiles'));
  }

  /**
   * Tests optional dates on news rows following the main item.
   */
  #[DataProvider('publicationDateProvider')]
  public function testOptionalPublicationDates(string $stored_date, string $expected_date, string $expected_datetime): void {
    $plugin = $this->createFeatureBlock([
      $this->createItem('hero', 0),
      $this->createItem('featured', 1, ['publication_date' => $stored_date]),
    ]);

    $build = $plugin->buildContent();
    $this->assertCount(1, $build['#featured']);
    $this->assertSame($expected_date, $build['#featured'][0]['date']);
    $this->assertSame($expected_datetime, $build['#featured'][0]['datetime']);
    $this->assertNotNull($build['#featured'][0]['thumbnail']);
  }

  /**
   * Supplies a valid calendar date and missing or invalid dates.
   */
  public static function publicationDateProvider(): array {
    return [
      'not configured' => ['', '', ''],
      'valid date' => ['2026-10-05', '5 October 2026', '2026-10-05'],
      'invalid calendar date' => ['2026-02-30', '', ''],
      'malformed date' => ['yesterday', '', ''],
    ];
  }

  /**
   * Tests dependencies required by the block's persistent content cache.
   */
  public function testSelectedFilesAndImageStylesAreCacheDependencies(): void {
    foreach (['feature_fallback', 'feature_mapped', 'link_carousel_feature_thumbnail'] as $id) {
      ImageStyle::create(['name' => $id, 'label' => $id])->save();
    }
    ResponsiveImageStyle::create([
      'id' => 'link_carousel_feature',
      'label' => 'Feature image',
      'fallback_image_style' => 'feature_fallback',
      'image_style_mappings' => [
        [
          'image_mapping_type' => 'image_style',
          'image_mapping' => 'feature_mapped',
          'breakpoint_id' => 'feature.large',
          'multiplier' => '1x',
        ],
      ],
    ])->save();
    $hero_file = $this->createImageFile();
    $featured_file = $this->createImageFile();
    $inactive_file = $this->createImageFile();
    $items = [
      $this->createItem('hero', 0, ['image' => [$hero_file->id()]]),
      $this->createItem('featured', 1, ['image' => [$featured_file->id()]]),
    ];
    foreach (range(2, 4) as $index) {
      $items[] = $this->createItem((string) $index, $index);
    }
    $items[] = $this->createItem('inactive', 5, ['image' => [$inactive_file->id()]]);
    $plugin = $this->createFeatureBlock($items);

    $build = $plugin->buildContent();
    $this->assertContains('file:' . $hero_file->id(), $build['#cache']['tags']);
    $this->assertContains('file:' . $featured_file->id(), $build['#cache']['tags']);
    $this->assertNotContains('file:' . $inactive_file->id(), $build['#cache']['tags']);
    $this->assertContains('config:responsive_image.styles.link_carousel_feature', $build['#cache']['tags']);
    foreach (['feature_fallback', 'feature_mapped', 'link_carousel_feature_thumbnail'] as $id) {
      $this->assertContains('config:image.style.' . $id, $build['#cache']['tags']);
      $cache = $this->container->get('cache.default');
      $cache->set('link_carousel_image_style_test', $build, Cache::PERMANENT, $build['#cache']['tags']);
      $this->assertNotFalse($cache->get('link_carousel_image_style_test'));

      // Saving a mapped style alone does not update the responsive style.
      ImageStyle::load($id)->set('label', 'Updated ' . $id)->save();
      $this->assertFalse($cache->get('link_carousel_image_style_test'));
    }
  }

  /**
   * Tests escaping, real links, image credits, alt text and static assets.
   */
  public function testPublicMarkupEscapesEditorialTextAndUsesStaticLibrary(): void {
    // A fallback-only responsive style is enough to test image attributes.
    ImageStyle::create(['name' => 'link_carousel_feature_thumbnail', 'label' => 'Feature thumbnail'])->save();
    ResponsiveImageStyle::create([
      'id' => 'link_carousel_feature',
      'label' => 'Feature image',
      'fallback_image_style' => 'link_carousel_feature_thumbnail',
    ])->save();
    $hero = $this->createItem('hero', 0, [
      'description' => '<script>alert("description")</script>',
      'tag_line' => '<em>Campaign</em>',
      'image_alt' => 'A family & their home',
      'image_caption' => '',
      'image_credit' => '<b>Photo credit</b>',
    ]);
    $hero['config']['label'] = '<script>alert("title")</script>';
    $featured = $this->createItem('featured', 1, ['publication_date' => '2026-10-05']);
    $featured['config']['label'] = '<strong>Featured story</strong>';
    $cta = $this->createCallToActionConfiguration([
      'description' => '<script>alert("action")</script>',
      'button_label' => '<b>Donate here</b>',
    ], '<em>Support relief</em>');
    $plugin = $this->createFeatureBlock([$hero, $featured], ['cta' => $cta]);

    $build = $plugin->buildContent();
    $this->assertSame('<em>Campaign</em>', $plugin->getBlockConfig()['links']['items'][0]['config']['value']['tag_line']);
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;description&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;strong&gt;Featured story&lt;/strong&gt;', $html);
    $this->assertStringContainsString('&lt;b&gt;Photo credit&lt;/b&gt;', $html);
    $this->assertStringContainsString('&lt;em&gt;Support relief&lt;/em&gt;', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;action&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;b&gt;Donate here&lt;/b&gt;', $html);
    $this->assertStringContainsString('alt="A family &amp; their home"', $html);
    $this->assertStringContainsString('datetime="2026-10-05"', $html);
    $this->assertStringContainsString('href="https://example.com/featured"', $html);
    $this->assertStringContainsString('aria-hidden="true"', $html);
    $this->assertStringContainsString('link-carousel-feature__cta-icon', $html);
    $this->assertStringContainsString('link-carousel-feature__cta-title', $html);
    $this->assertStringNotContainsString('link-carousel-feature__donation', $html);
    $this->assertStringNotContainsString('Campaign', $html);
    $this->assertStringNotContainsString('link-carousel-feature__tag-line', $html);
    $this->assertStringNotContainsString('swiper', $html);
    $this->assertContains('common_design_subtheme/ghi-link-carousel-feature', $build['#attached']['library']);
    $this->assertNotContains('common_design_subtheme/ghi-link-carousel', $build['#attached']['library']);
    $this->assertNotContains('common_design_subtheme/swiper', $build['#attached']['library']);
  }

  /**
   * Tests only the main image exposes its escaped caption through an i control.
   */
  #[DataProvider('imageInformationProvider')]
  public function testMainImageInformationOverlay(string $caption, string $credit): void {
    ImageStyle::create(['name' => 'link_carousel_feature_thumbnail', 'label' => 'Feature thumbnail'])->save();
    ResponsiveImageStyle::create([
      'id' => 'link_carousel_feature',
      'label' => 'Feature image',
      'fallback_image_style' => 'link_carousel_feature_thumbnail',
    ])->save();
    $plugin = $this->createFeatureBlock([
      $this->createItem('hero', 0, ['image_caption' => $caption, 'image_credit' => $credit]),
      $this->createItem('featured', 1),
    ]);

    $build = $plugin->buildContent();
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $xpath = new \DOMXPath(Html::load($html));
    $toggles = $xpath->query('//button[@class="link-carousel-feature__caption-toggle"]');
    $this->assertSame($caption !== '' || $credit !== '' ? 1 : 0, $toggles->length);
    if (!$toggles->length) {
      $this->assertStringNotContainsString('link-carousel-feature__caption', $html);
      return;
    }

    $toggle = $toggles->item(0);
    $this->assertSame('button', $toggle->getAttribute('type'));
    $this->assertSame('Image information', $toggle->getAttribute('aria-label'));
    $this->assertSame('true', $toggle->firstElementChild->getAttribute('aria-hidden'));
    $caption_id = $toggle->getAttribute('aria-describedby');
    $this->assertNotEmpty($caption_id);
    $content = $xpath->query('//div[@id="' . $caption_id . '"]')->item(0);
    $this->assertNotNull($content);
    $this->assertSame('link-carousel-feature__caption-content', $content->getAttribute('class'));
    $this->assertSame('0', $content->getAttribute('tabindex'));
    $this->assertSame('figcaption', $content->parentNode->nodeName);
    $this->assertSame('figure', $content->parentNode->parentNode->nodeName);
    $this->assertSame(trim($caption . ' ' . $credit), preg_replace('/\s+/', ' ', trim($content->textContent)));
    $this->assertSame(0, $xpath->query('.//em | .//b', $content)->length);
    $this->assertSame('eager', $build['#highlighted']['image']['#attributes']['loading']);
    $this->assertSame('high', $build['#highlighted']['image']['#attributes']['fetchpriority']);
  }

  /**
   * Supplies complete, partial and missing main-image information.
   */
  public static function imageInformationProvider(): array {
    return [
      'caption and credit' => ['<em>Image caption</em>', '<b>Image credit</b>'],
      'caption only' => ['Image caption', ''],
      'credit only' => ['', 'Image credit'],
      'neither configured' => ['', ''],
    ];
  }

  /**
   * Tests the heart follows the selected CTA rather than the panel as a whole.
   */
  public function testOptionalHeartFollowsSelectedCallToAction(): void {
    $items = [
      $this->createCallToActionItem(1, 0, ['show_heart' => TRUE]),
      $this->createCallToActionItem(2, 1, ['show_heart' => FALSE]),
    ];
    foreach ([1 => TRUE, 2 => FALSE] as $id => $show_heart) {
      $plugin = $this->createFeatureBlock([], ['cta' => ['items' => $items, 'active_item_id' => $id]]);
      $build = $plugin->buildContent();
      $html = (string) $this->container->get('renderer')->renderRoot($build);
      if ($show_heart) {
        $this->assertStringContainsString('link-carousel-feature__cta-icon', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
      }
      else {
        $this->assertStringNotContainsString('link-carousel-feature__cta-icon', $html);
        $this->assertStringNotContainsString('♥', $html);
      }
      $this->assertStringContainsString('Donate here', $html);
      $this->assertStringContainsString('opens in a new tab', $html);
    }
  }

  /**
   * Tests every external link has an icon class and an accessible new-tab hint.
   */
  public function testExternalLinksShareVisualAndAccessibleIndicators(): void {
    $plugin = $this->createFeatureBlock([
      $this->createItem('hero', 0),
      $this->createItem('featured', 1),
    ], ['cta' => $this->createCallToActionConfiguration()]);
    $build = $plugin->buildContent();
    foreach ([$build['#highlighted']['button'], $build['#featured'][0]['button'], $build['#call_to_action']['button']] as $link) {
      $attributes = $link['#url']->getOption('attributes');
      $this->assertContains('external', $attributes['class']);
      $this->assertSame('_blank', $attributes['target']);
      $this->assertSame('noopener noreferrer', $attributes['rel']);
      $html = (string) $this->container->get('renderer')->renderRoot($link);
      $this->assertStringContainsString('visually-hidden', $html);
      $this->assertStringContainsString('opens in a new tab', $html);
    }
    $this->assertContains('cd-button', $build['#highlighted']['button']['#url']->getOption('attributes')['class']);
    $this->assertNotContains('cd-button', $build['#featured'][0]['button']['#url']->getOption('attributes')['class']);
  }

  /**
   * Tests local links keep normal navigation and internal button styling.
   */
  public function testInternalLinksKeepSameTabNavigationAndButtonArrows(): void {
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::allowed());
    $this->container->set('access_manager', $access_manager);
    $plugin = $this->createFeatureBlock([
      $this->createItem('hero', 0, ['url' => 'internal:/admin']),
      $this->createItem('featured', 1, ['url' => 'internal:/admin']),
    ], ['cta' => $this->createCallToActionConfiguration(['url' => 'internal:/admin'])]);
    $build = $plugin->buildContent();
    foreach ([$build['#highlighted']['button'], $build['#featured'][0]['button'], $build['#call_to_action']['button']] as $index => $link) {
      $attributes = $link['#url']->getOption('attributes');
      $classes = $attributes['class'] ?? [];
      $this->assertNotContains('external', $classes);
      $this->assertArrayNotHasKey('target', $attributes);
      if ($index === 1) {
        $this->assertNotContains('read-more', $classes);
      }
      else {
        $this->assertContains('cd-button', $classes);
        $this->assertContains('read-more', $classes);
      }
      $html = (string) $this->container->get('renderer')->renderRoot($link);
      $this->assertStringNotContainsString('opens in a new tab', $html);
    }
  }

  /**
   * Creates a block with canonical link settings.
   */
  private function createFeatureBlock(array $items = [], array $configuration = []): LinkCarousel {
    return $this->createBlockPlugin('generic_link_carousel', $configuration + [
      'links' => [
        'items' => $items,
      ],
    ]);
  }

  /**
   * Creates an editorial item with a real image file unless overridden.
   */
  private function createItem(string $id, int $weight = 0, array $values = []): array {
    return [
      'id' => $id,
      'pid' => NULL,
      'item_type' => 'carousel_item',
      'weight' => $weight,
      'config' => [
        'label' => 'Item ' . $id,
        'value' => $values + [
          'tag_line' => 'Campaign',
          'description' => 'A description for ' . $id,
          'image' => $values['image'] ?? [$this->createImageFile()->id()],
          'image_alt' => 'An image for ' . $id,
          'image_caption' => 'A caption for ' . $id,
          'image_credit' => 'A credit for ' . $id,
          'url' => 'https://example.com/' . $id,
          'button_label' => 'Read more',
          'publication_date' => '',
        ],
      ],
    ];
  }

  /**
   * Creates CTA settings with one selected item.
   */
  private function createCallToActionConfiguration(array $values = [], string $title = 'Donate to relief organizations'): array {
    return [
      'items' => [$this->createCallToActionItem(1, 0, $values, $title)],
      'active_item_id' => 1,
      'source_items' => [],
    ];
  }

  /**
   * Creates a saved call-to-action item without loading any image files.
   */
  private function createCallToActionItem(int|string $id, int $weight = 0, array $values = [], string $title = 'Donate to relief organizations'): array {
    return [
      'id' => $id,
      'pid' => NULL,
      'item_type' => 'call_to_action',
      'weight' => $weight,
      'config' => [
        'label' => $title,
        'value' => $values + [
          'description' => 'Help relief organizations reach people affected by crises.',
          'url' => 'https://crisisrelief.un.org/donate',
          'button_label' => 'Donate here',
          'show_heart' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Creates a real file entity backed by a core image fixture.
   */
  private function createImageFile(): FileInterface {
    $filename = $this->randomMachineName() . '.png';
    $uri = $this->container->get('file_system')->copy(DRUPAL_ROOT . '/core/tests/fixtures/files/image-1.png', 'public://' . $filename);
    $file = File::create([
      'filename' => $filename,
      'uri' => $uri,
      'filemime' => 'image/png',
    ]);
    $file->save();
    return $file;
  }

}
