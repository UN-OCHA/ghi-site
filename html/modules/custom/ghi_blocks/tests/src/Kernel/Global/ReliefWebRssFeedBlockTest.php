<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Generic\ReliefWebRssFeed;
use Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed as ReliefWebRssFeedService;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;

/**
 * Tests the ReliefWeb RSS feed block plugin.
 *
 * @group ghi_blocks
 */
class ReliefWebRssFeedBlockTest extends BlockKernelTestBase {

  public const FEED_URL = 'https://reliefweb.int/country/ven/rss.xml?format=10';
  private const VIEW_MORE_URL = 'https://reliefweb.int/updates?advanced-search=%28C254%29_%28F10%29';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setMockReliefWebRssFeed($this->buildFeedItems());
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties(): void {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(ReliefWebRssFeed::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertSame('Latest situation reports', $plugin->metadata()->defaultTitle);
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $plugin->metadata()->configForms);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $plugin->metadata()->configForms);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild(): void {
    $plugin = $this->getBlockPlugin(3, self::VIEW_MORE_URL, 'More situation reports');
    $build = $plugin->buildContent();

    $this->assertSame('reliefweb_rss_feed', $build['#theme']);
    $this->assertCount(3, $build['#items']);
    $this->assertSame('UNICEF Venezuela Humanitarian Situation Report No. 8', $build['#items'][0]['title']);
    $this->assertSame('link', $build['#items'][0]['link']['#type']);
    $this->assertSame('2 Sep 2026', $build['#items'][0]['formatted_date']);
    $this->assertSame("UN Children's Fund", $build['#items'][0]['source']);
    $this->assertSame('link', $build['#view_more_link']['#type']);
    $this->assertSame('More situation reports', $build['#view_more_link']['#title']);
    $this->assertInstanceOf(Url::class, $build['#view_more_link']['#url']);
    $this->assertContains('cd-button', $build['#view_more_link']['#url']->getOption('attributes')['class']);
    $this->assertSame(3600, $build['#cache']['max-age']);
    $this->assertSame(['reliefweb_rss_feed:test'], $build['#cache']['tags']);
  }

  /**
   * Tests the block build with no usable feed data.
   */
  public function testBlockBuildNoItems(): void {
    $plugin = $this->getBlockPlugin();
    $this->assertFalse($plugin->isEmpty());

    $this->setMockReliefWebRssFeed([]);
    $plugin = $this->getBlockPlugin();
    $this->assertNull($plugin->buildContent());

    $plugin = $this->createBlockPlugin('generic_reliefweb_rss_feed', []);
    $this->assertTrue($plugin->isEmpty());
    $this->assertNull($plugin->buildContent());
  }

  /**
   * Tests the block forms and validation.
   */
  public function testBlockFormsAndValidation(): void {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $form = $plugin->feedForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('feed_url', $form);
    $this->assertArrayHasKey('#required_error', $form['feed_url']);

    $form['feed_url']['#parents'] = ['container', 'feed_url'];
    $form_state->setValue(['feed', 'feed_url'], self::FEED_URL);
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertEmpty($form_state->getErrors());

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $form_state->setValue(['feed', 'feed_url'], 'https://example.com/rss.xml');
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertNotEmpty($form_state->getErrors());

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'display');
    $display_form = $plugin->displayForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('item_count', $display_form);
    $this->assertArrayHasKey('view_more_url', $display_form);
    $this->assertArrayHasKey('view_more_label', $display_form);

    $display_form['item_count']['#parents'] = ['container', 'item_count'];
    $display_form['view_more_url']['#parents'] = ['container', 'view_more_url'];
    $form_state->setValue(['display', 'item_count'], 7);
    $form_state->setValue(['display', 'view_more_url'], '/internal-link');
    $plugin->blockValidate(['container' => $display_form], $form_state);
    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Tests that the feed URL is retained when navigating tabs the first time.
   */
  public function testFeedUrlRetainedOnFirstTabNavigation(): void {
    $plugin = $this->createBlockPlugin('generic_reliefweb_rss_feed', []);

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $plugin->setFormState($form_state);

    $initial_settings = $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);
    $this->assertNull($initial_settings['feed']['feed_url']);

    $form_state->setValue(['feed', 'feed_url'], self::FEED_URL);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'subforms', 'display'],
    ]);
    $element = [];
    ReliefWebRssFeed::ajaxMultiStepSubmit($element, $form_state);

    $settings = $this->callPrivateMethod($plugin, 'getTemporarySettings', [$form_state]);
    $this->assertSame('display', $form_state->get('current_subform'));
    $this->assertSame(self::FEED_URL, $settings['feed']['feed_url']);
  }

  /**
   * Tests that an empty feed URL is validated when entering preview.
   */
  public function testEmptyFeedUrlValidatedOnPreview(): void {
    $plugin = $this->createBlockPlugin('generic_reliefweb_rss_feed', []);

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $form_state->setValue(['feed', 'feed_url'], '');
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'subforms', 'preview'],
      '#default_value' => FALSE,
    ]);
    $complete_form = [
      '#parents' => [],
      '#array_parents' => [],
      'settings' => [
        '#parents' => [],
        '#array_parents' => [],
        'container' => [
          'feed_url' => [
            '#parents' => ['feed', 'feed_url'],
          ],
        ],
      ],
    ];
    $form_state->setCompleteForm($complete_form);
    $element = [];
    $plugin->blockElementValidate($element, $form_state);

    $this->assertArrayHasKey('feed][feed_url', $form_state->getErrors());
    $this->assertFalse($form_state->get('preview'));
  }

  /**
   * Tests that an empty feed URL is validated when submitting from preview.
   */
  public function testEmptyFeedUrlValidatedOnSubmitFromPreview(): void {
    $plugin = $this->createBlockPlugin('generic_reliefweb_rss_feed', []);

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $form_state->set('preview', TRUE);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'submit'],
    ]);
    $plugin->setFormState($form_state);

    $form = [
      'container' => [
        'preview' => [],
      ],
    ];
    $plugin->blockValidate($form, $form_state);

    $this->assertArrayHasKey('feed][feed_url', $form_state->getErrors());
    $this->assertFalse($form_state->get('preview'));
  }

  /**
   * Tests that the feed URL is retained when submitting from preview.
   */
  public function testFeedUrlRetainedOnSubmitFromPreview(): void {
    $plugin = $this->createBlockPlugin('generic_reliefweb_rss_feed', []);

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form_state->set('current_subform', 'feed');
    $form_state->set('original_submit_handlers', []);
    $plugin->setFormState($form_state);

    $form_state->setValue(['feed', 'feed_url'], self::FEED_URL);
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
    $element = [];
    $plugin->blockElementValidate($element, $form_state);

    $form_state->setValues([]);
    $form_state->setTriggeringElement([
      '#parents' => ['actions', 'submit'],
    ]);
    $form = [
      '#submit' => [],
      'container' => [
        'preview' => [],
      ],
      'actions' => [
        'submit' => [
          '#parents' => ['actions', 'submit'],
        ],
      ],
    ];
    $plugin->blockValidate($form, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $plugin->submitForm($form, $form_state);
    $plugin->blockSubmit($form, $form_state);

    $configuration = $plugin->getConfiguration()['hpc'];
    $this->assertSame(self::FEED_URL, $configuration['feed']['feed_url']);
  }

  /**
   * Register a mocked ReliefWeb RSS feed service.
   *
   * @param array $items
   *   The items to return.
   * @param bool $valid_url
   *   Whether the configured test URL should validate.
   */
  private function setMockReliefWebRssFeed(array $items, bool $valid_url = TRUE): void {
    $service = new class($items, $valid_url) extends ReliefWebRssFeedService {

      /**
       * Constructs a mock ReliefWeb RSS feed service.
       */
      public function __construct(private readonly array $items, private readonly bool $validUrl) {}

      /**
       * {@inheritdoc}
       */
      public function getItems(string $feed_url, int $limit): array {
        return array_slice($this->items, 0, $limit);
      }

      /**
       * {@inheritdoc}
       */
      public function isValidFeedUrl(string $feed_url): bool {
        return $this->validUrl && $feed_url === ReliefWebRssFeedBlockTest::FEED_URL;
      }

      /**
       * {@inheritdoc}
       */
      public function getCacheTag(string $feed_url): string {
        return 'reliefweb_rss_feed:test';
      }

    };

    $this->container->set('ghi_blocks.reliefweb_rss_feed', $service);
    \Drupal::setContainer($this->container);
  }

  /**
   * Get a block plugin.
   *
   * @param int $item_count
   *   The item count.
   * @param string|null $view_more_url
   *   The optional view more URL.
   * @param string|null $view_more_label
   *   The optional view more label.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\ReliefWebRssFeed
   *   The block plugin.
   */
  private function getBlockPlugin(int $item_count = 4, ?string $view_more_url = NULL, ?string $view_more_label = NULL): ReliefWebRssFeed {
    $configuration = [
      'feed' => [
        'feed_url' => self::FEED_URL,
      ],
      'display' => [
        'item_count' => $item_count,
        'view_more_url' => $view_more_url,
        'view_more_label' => $view_more_label,
      ],
    ];
    return $this->createBlockPlugin('generic_reliefweb_rss_feed', $configuration);
  }

  /**
   * Build feed items.
   *
   * @return array
   *   The feed items.
   */
  private function buildFeedItems(): array {
    return [
      [
        'title' => 'UNICEF Venezuela Humanitarian Situation Report No. 8',
        'url' => 'https://reliefweb.int/report/venezuela-bolivarian-republic/unicef-venezuela-humanitarian-situation-report-no-8',
        'timestamp' => strtotime('Wed, 02 Sep 2026 07:18:39 +0000'),
        'source' => "UN Children's Fund",
      ],
      [
        'title' => 'Venezuela: Earthquakes - LTC Situation Report #07',
        'url' => 'https://reliefweb.int/report/venezuela-bolivarian-republic/venezuela-earthquakes-ltc-situation-report-telecoms-07',
        'timestamp' => strtotime('Tue, 01 Sep 2026 12:47:31 +0000'),
        'source' => 'Emergency Telecommunications Cluster, Logistics Cluster',
      ],
      [
        'title' => 'Venezuela: Earthquake Response Situation Report #26',
        'url' => 'https://reliefweb.int/report/venezuela-bolivarian-republic/venezuela-earthquake-response-situation-report-26',
        'timestamp' => strtotime('Fri, 28 Aug 2026 20:19:44 +0000'),
        'source' => 'International Organization for Migration',
      ],
      [
        'title' => 'Earthquakes in Venezuela: Situation Report #32',
        'url' => 'https://reliefweb.int/report/venezuela-bolivarian-republic/earthquakes-venezuela-situation-report-32',
        'timestamp' => strtotime('Thu, 27 Aug 2026 18:00:00 +0000'),
        'source' => 'OCHA',
      ],
    ];
  }

}
