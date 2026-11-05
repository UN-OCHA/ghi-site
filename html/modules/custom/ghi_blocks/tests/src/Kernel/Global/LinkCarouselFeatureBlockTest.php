<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
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
    $this->assertSame($legacy_donation, $effective['donation']['source_items']['donation']);
    $build = $plugin->buildContent();
    $this->assertSame('link_carousel_feature', $build['#theme']);
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertSame(['Item featured'], array_column($build['#featured'], 'title'));
    $this->assertNotNull($build['#donation']);
    $this->assertSame($build['#highlighted']['image']['#uri'], $plugin->provideImageUri());
    $this->assertSame($items, $plugin->getConfiguration()['hpc']['items']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());

    $files = $this->callPrivateMethod($plugin, 'getFiles');
    $this->assertCount(3, $files);
    $this->assertArrayHasKey($legacy_donation['config']['value']['image'][0], $files);
    $this->assertNotContains('file:' . $legacy_donation['config']['value']['image'][0], $build['#cache']['tags']);
  }

  /**
   * Tests that an empty historical or new instance shows the default donation.
   */
  public function testEmptyUnconfiguredInstanceDisplaysDefaultDonation(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', []);
    $raw_configuration = $plugin->getConfiguration();

    $build = $plugin->buildContent();
    $this->assertSame('link_carousel_feature', $build['#theme']);
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertNotNull($build['#donation']);
    $this->assertTrue($plugin->getBlockConfig()['donation']['enabled']);
    $this->assertNull($plugin->provideImageUri());
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests that donation defaults fill only missing settings.
   */
  public function testDonationDefaultsOnlyFillMissingSettings(): void {
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'items' => [],
      'donation' => ['title' => 'Custom donation title'],
    ]);
    $raw_configuration = $plugin->getConfiguration();

    $effective = $plugin->getBlockConfig();
    $this->assertTrue($effective['donation']['enabled']);
    $this->assertSame('Custom donation title', $effective['donation']['title']);
    foreach (['description', 'url', 'button_label'] as $field) {
      $this->assertNotSame('', trim($effective['donation'][$field]));
    }
    $this->assertSame('Custom donation title', $plugin->buildContent()['#donation']['title']);
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
  }

  /**
   * Tests that a retained donation slide cannot re-enable an explicit opt-out.
   */
  public function testDisabledDonationIsNotOverriddenByOriginalSlide(): void {
    $items = [
      $this->createItem('donation', -100, ['url' => 'https://crisisrelief.un.org/en/donate']),
      $this->createItem('news', 0),
    ];
    $plugin = $this->createBlockPlugin('generic_link_carousel', [
      'items' => $items,
      'donation' => ['enabled' => FALSE],
    ]);
    $raw_configuration = $plugin->getConfiguration();

    $build = $plugin->buildContent();
    $this->assertFalse($plugin->getBlockConfig()['donation']['enabled']);
    $this->assertNull($build['#donation']);
    $this->assertSame('Item news', $build['#highlighted']['title']);
    $this->assertSame([], $build['#featured']);
    $this->assertCount(2, $this->callPrivateMethod($plugin, 'getFiles'));
    $this->assertSame($raw_configuration, $plugin->getConfiguration());
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
    // availability result for news and the independent donation panel.
    $uri = 'entity:node/' . $node->id();
    $this->assertSame($uri, LinkCarouselLink::normalize($uri));
    $cache = new CacheableMetadata();
    $resolved = LinkCarouselLink::resolve($uri, $cache);
    $this->assertNull($resolved['url']);
    $this->assertSame(['This link points to unpublished content.'], array_map('strval', $resolved['issues']));
    $this->assertContains('node:' . $node->id(), $cache->getCacheTags());
    $donation = $this->createDonationConfiguration();
    $donation['url'] = $uri;
    $donation_plugin = $this->createFeatureBlock([], ['donation' => $donation]);
    $this->assertNull($donation_plugin->buildContent()['#donation']);
    $form = $donation_plugin->donationForm([], new FormState());
    $this->assertDonationFieldFeedback($form, ['url' => 'This link points to unpublished content.']);
    $this->assertContains('node:' . $node->id(), $form['#cache']['tags']);
    $this->assertContains('user.permissions', $form['#cache']['contexts']);
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
   * Tests that the donation action can be displayed without any news items.
   */
  public function testDonationDoesNotRequireNewsItems(): void {
    $plugin = $this->createFeatureBlock([], ['donation' => $this->createDonationConfiguration()]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertSame('Donate to relief organizations', $build['#donation']['title']);
    $this->assertSame('https://crisisrelief.un.org/donate', $build['#donation']['button']['#url']->toString());
    $this->assertSame(['cd-button'], $build['#donation']['button']['#options']['attributes']['class']);
    $this->assertSame('_blank', $build['#donation']['button']['#url']->getOption('attributes')['target']);
    $this->assertSame('noopener noreferrer', $build['#donation']['button']['#url']->getOption('attributes')['rel']);
    $this->assertNull($plugin->provideImageUri());
  }

  /**
   * Tests that an unresolved donation destination can become available later.
   */
  public function testUnavailableDonationDestinationKeepsCacheDependencies(): void {
    $donation = $this->createDonationConfiguration();
    $donation['url'] = 'internal:/hpc-10626-future-donation';
    $plugin = $this->createFeatureBlock([], ['donation' => $donation]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#donation']);
    $this->assertContains('node_list', $build['#cache']['tags']);
    $this->assertContains('route_match', $build['#cache']['tags']);
    $this->assertSame($donation, $plugin->getBlockConfig()['donation']);
    $form = $plugin->donationForm([], new FormState());
    $this->assertDonationFieldFeedback($form, ['url' => 'Enter a valid URL or an existing content link.']);
    $this->assertContains('node_list', $form['#cache']['tags']);
    $this->assertContains('route_match', $form['#cache']['tags']);
  }

  /**
   * Tests donation feedback and rendering share anonymous access decisions.
   */
  public function testDonationFeedbackTracksAnonymousAccess(): void {
    $donation = $this->createDonationConfiguration();
    $donation['url'] = 'internal:/admin';
    $plugin = $this->createFeatureBlock([], ['donation' => $donation]);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::forbidden()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);

    $form = $plugin->donationForm([], new FormState());
    $this->assertDonationFieldFeedback($form, ['url' => 'This link is not currently accessible to anonymous visitors.']);
    $this->assertContains('user.permissions', $form['#cache']['contexts']);
    $build = $plugin->buildContent();
    $this->assertNull($build['#donation']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
    $this->assertContains('route_match', $build['#cache']['tags']);

    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::allowed()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);
    $form = $plugin->donationForm([], new FormState());
    $this->assertDonationFieldFeedback($form, []);
    $this->assertNotNull($plugin->buildContent()['#donation']);
    $this->assertSame($donation, $plugin->getBlockConfig()['donation']);
  }

  /**
   * Tests that incomplete saved donation settings remain absent publicly.
   */
  #[DataProvider('incompleteDonationProvider')]
  public function testIncompleteDonationIsNotDisplayed(string $field): void {
    $donation = $this->createDonationConfiguration();
    $donation[$field] = '';
    $plugin = $this->createFeatureBlock([$this->createItem('hero')], ['donation' => $donation]);

    $build = $plugin->buildContent();
    $this->assertSame('Item hero', $build['#highlighted']['title']);
    $this->assertNull($build['#donation']);
    $this->assertSame('', $plugin->getBlockConfig()['donation'][$field]);
    $form = $plugin->donationForm([], new FormState());
    $expected = [
      'title' => 'Add a title for the donation panel.',
      'description' => 'Add a description for the donation panel.',
      'url' => 'Enter a valid URL or an existing content link.',
      'button_label' => 'Add a button label for the donation panel.',
    ];
    $this->assertDonationFieldFeedback($form, [$field => $expected[$field]]);
    $this->assertSame('', $form[$field]['#default_value']);
  }

  /**
   * Supplies each field required for a complete public donation action.
   */
  public static function incompleteDonationProvider(): array {
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
    $donation = $this->createDonationConfiguration();
    $donation['source_items'] = [
      $this->createItem('archived-donation', -100, [
        'image' => [$source_file->id()],
        'url' => 'https://crisisrelief.un.org/donate',
      ]),
    ];
    $plugin = $this->createFeatureBlock([], ['donation' => $donation]);

    $build = $plugin->buildContent();
    $this->assertNull($build['#highlighted']);
    $this->assertSame([], $build['#featured']);
    $this->assertNotNull($build['#donation']);
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
    $donation = $this->createDonationConfiguration();
    $donation['enabled'] = FALSE;
    $donation['source_items'] = [
      $this->createItem('archived', 0, ['image' => [$archived_file->id()]]),
      $this->createItem('shared-archive', 1, ['image' => [$news_file->id()]]),
    ];
    $plugin = $this->createFeatureBlock($items, [
      'links' => ['items' => $items, 'display_end_weight' => 1],
      'donation' => $donation,
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
   * Tests escaping, real links, visible credits, alt text and static assets.
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
    $donation = $this->createDonationConfiguration();
    $donation['title'] = '<em>Support relief</em>';
    $donation['description'] = '<script>alert("donation")</script>';
    $donation['button_label'] = '<b>Donate here</b>';
    $plugin = $this->createFeatureBlock([$hero, $featured], ['donation' => $donation]);

    $build = $plugin->buildContent();
    $this->assertSame('<em>Campaign</em>', $plugin->getBlockConfig()['links']['items'][0]['config']['value']['tag_line']);
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;description&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;strong&gt;Featured story&lt;/strong&gt;', $html);
    $this->assertStringContainsString('&lt;b&gt;Photo credit&lt;/b&gt;', $html);
    $this->assertStringContainsString('&lt;em&gt;Support relief&lt;/em&gt;', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;donation&quot;)&lt;/script&gt;', $html);
    $this->assertStringContainsString('&lt;b&gt;Donate here&lt;/b&gt;', $html);
    $this->assertStringContainsString('alt="A family &amp; their home"', $html);
    $this->assertStringContainsString('datetime="2026-10-05"', $html);
    $this->assertStringContainsString('href="https://example.com/featured"', $html);
    $this->assertStringContainsString('aria-hidden="true"', $html);
    $this->assertStringContainsString('link-carousel-feature__donation-icon', $html);
    $this->assertStringNotContainsString('Campaign', $html);
    $this->assertStringNotContainsString('link-carousel-feature__tag-line', $html);
    $this->assertStringNotContainsString('swiper', $html);
    $this->assertContains('common_design_subtheme/ghi-link-carousel-feature', $build['#attached']['library']);
    $this->assertNotContains('common_design_subtheme/ghi-link-carousel', $build['#attached']['library']);
    $this->assertNotContains('common_design_subtheme/swiper', $build['#attached']['library']);
  }

  /**
   * Asserts availability is communicated on the relevant nonblocking fields.
   */
  private function assertDonationFieldFeedback(array $form, array $expected_issues): void {
    $this->assertArrayNotHasKey('availability', $form);
    $this->assertArrayHasKey('caption', $form);
    foreach (['title', 'description', 'url', 'button_label'] as $key) {
      $classes = $form[$key]['#wrapper_attributes']['class'] ?? [];
      $description = (string) ($form[$key]['#description'] ?? '');
      if (isset($expected_issues[$key])) {
        $this->assertContains('link-carousel-editor__field--unavailable', $classes);
        $this->assertStringContainsString('<strong>Not available:</strong>', $description);
        $this->assertStringContainsString($expected_issues[$key], $description);
      }
      else {
        $this->assertNotContains('link-carousel-editor__field--unavailable', $classes);
        $this->assertStringNotContainsString('Not available:', $description);
      }
      $this->assertArrayNotHasKey('#errors', $form[$key]);
      $this->assertArrayNotHasKey('aria-invalid', $form[$key]['#attributes'] ?? []);
    }
    $this->assertStringContainsString('HTTP or HTTPS', (string) $form['url']['#description']);
    $this->assertContains('route_match', $form['#cache']['tags']);
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
   * Creates independent donation settings.
   */
  private function createDonationConfiguration(): array {
    return [
      'enabled' => TRUE,
      'title' => 'Donate to relief organizations',
      'description' => 'Help relief organizations reach people affected by crises.',
      'url' => 'https://crisisrelief.un.org/donate',
      'button_label' => 'Donate here',
      'source_items' => [],
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
