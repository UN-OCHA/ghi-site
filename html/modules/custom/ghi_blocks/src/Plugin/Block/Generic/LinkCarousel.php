<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Helpers\LinkCarouselLink;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\Block\ImageProviderBlockInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem;
use Drupal\ghi_blocks\Traits\ManagedFileBlockTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_form_elements\Element\ConfigurationContainer;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'LinkCarousel' block.
 */
#[Block(
  id: 'generic_link_carousel',
  admin_label: new TranslatableMarkup('Link Carousel'),
  category: new TranslatableMarkup('Generic elements'),
)]
class LinkCarousel extends GHIBlockBase implements ConfigurableTableBlockInterface, ImageProviderBlockInterface, MultiStepFormBlockInterface {

  use ConfigurationContainerTrait;
  use ManagedFileBlockTrait;

  /**
   * The maximum number of active featured items.
   */
  public const MAX_FEATURED_ITEMS = 4;

  /**
   * The synthetic editor row; it is never stored as a news item.
   */
  public const DISPLAY_END_ROW = '_display_end';

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      usesTitle: FALSE,
      configForms: [
        'links' => [
          'title' => 'Links',
          'callback' => 'linksForm',
          'base_form' => TRUE,
        ],
        'donation' => [
          'title' => 'Donation',
          'callback' => 'donationForm',
        ],
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function provideImageUri() {
    $conf = $this->getBlockConfig();
    $cache = new CacheableMetadata();
    foreach ($this->getNewsItems($conf) as $item) {
      $plugin = $this->getItemTypePluginForColumn($item);
      if (!$plugin->getAvailabilityIssues($cache)) {
        return $plugin->getImage()?->getFileUri();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();
    $items = $this->getNewsItems($conf);
    $cache = $this->getImageStyleCacheMetadata();
    // Alias changes can affect which news and donation links are available.
    $cache->addCacheTags(['route_match']);

    // Unavailable records remain editable but do not consume display positions.
    $highlighted = NULL;
    $featured = [];
    foreach ($items as $item) {
      $build = $this->buildFeatureItem($item, $cache, $highlighted === NULL);
      if (!$build) {
        continue;
      }
      if ($highlighted === NULL) {
        $highlighted = $build;
      }
      else {
        $featured[] = $build;
      }
      if (count($featured) === self::MAX_FEATURED_ITEMS) {
        break;
      }
    }

    $donation = NULL;
    $settings = $conf['donation'] ?? [];
    if (!empty($settings['enabled'])) {
      $availability = $this->getDonationAvailability($settings, $cache);
      if (!$availability['issues']) {
        $button = $this->buildFeatureLink($availability['url'], $settings['button_label'], TRUE);
        $button['#title'] = [
          'icon' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => '♥',
            '#attributes' => [
              'class' => ['link-carousel-feature__donation-icon'],
              'aria-hidden' => 'true',
            ],
          ],
          'label' => ['#plain_text' => $settings['button_label'] ?? ''],
        ];
        // The heart identifies this action; don't add the external-link icon.
        $button['#options']['attributes']['class'] = ['cd-button'];
        $donation = [
          'title' => $settings['title'] ?? '',
          'description' => $settings['description'] ?? '',
          'button' => $button,
        ];
      }
    }

    $build = [
      '#theme' => 'link_carousel_feature',
      '#highlighted' => $highlighted,
      '#featured' => $featured,
      '#donation' => $donation,
    ];
    // Theme variables are not render-array children. Explicitly carry file,
    // destination and access dependencies, including those of skipped items.
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Collects responsive source and thumbnail image-style dependencies.
   */
  private function getImageStyleCacheMetadata(): CacheableMetadata {
    $cache = new CacheableMetadata();
    $cache->addCacheTags(['config:responsive_image.styles.link_carousel_feature']);
    $style_ids = ['link_carousel_feature_thumbnail'];
    $responsive_style = ResponsiveImageStyle::load('link_carousel_feature');
    if ($responsive_style) {
      $cache->addCacheableDependency($responsive_style);
      $style_ids = array_unique(array_merge($style_ids, $responsive_style->getImageStyleIds()));
    }
    // Keep each style's tag even when the style cannot be loaded.
    $cache->addCacheTags(array_map(static fn(string $id): string => 'config:image.style.' . $id, $style_ids));
    foreach (ImageStyle::loadMultiple($style_ids) as $style) {
      $cache->addCacheableDependency($style);
    }
    return $cache;
  }

  /**
   * Returns supported item records keyed by their stable IDs, in weight order.
   */
  private function getNewsItems(array $conf): array {
    $items = array_filter($conf['links']['items'], 'is_array');
    ArrayHelper::sortArrayByNumericKey($items, 'weight', SORT_ASC);
    $selected = [];
    foreach ($items as $item) {
      if (is_array($item) && isset($item['id']) && ($item['item_type'] ?? NULL) === 'carousel_item') {
        // Apply the physical cutoff before availability checks: invalid news
        // above it must not promote an excluded item from below it.
        if ($conf['links']['display_end_weight'] !== NULL && (int) $item['weight'] >= $conf['links']['display_end_weight']) {
          continue;
        }
        $selected[(string) $item['id']] = $item;
      }
    }
    return $selected;
  }

  /**
   * Builds an available item while retaining dependencies of skipped records.
   */
  private function buildFeatureItem(array $item, CacheableMetadata $cache, bool $highlighted = FALSE): ?array {
    $value = $item['config']['value'] ?? [];
    $title = $item['config']['label'] ?? '';
    /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem $plugin */
    $plugin = $this->getItemTypePluginForColumn($item);
    $resolved_link = LinkCarouselLink::resolve($value['url'] ?? '', $cache);
    if ($plugin->getAvailabilityIssues($cache, $resolved_link)) {
      return NULL;
    }
    $label = $highlighted ? (($value['button_label'] ?? '') ?: $this->t('Read more')) : $title;
    $link = $this->buildFeatureLink($resolved_link['url'], $label, $highlighted);
    $file = $plugin->getImage();

    $date = $value['publication_date'] ?? '';
    $datetime = $date ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC')) : FALSE;
    if (!$datetime || $datetime->format('Y-m-d') !== $date) {
      $date = '';
      $datetime = FALSE;
    }
    return [
      'title' => $title,
      'description' => $value['description'] ?? '',
      'caption' => $value['image_caption'] ?? '',
      'credit' => $value['image_credit'] ?? '',
      'button' => $link,
      'datetime' => $date,
      'date' => $datetime ? $this->dateFormatter->format($datetime->getTimestamp(), 'custom', 'j F Y', 'UTC') : '',
      'image' => $file ? [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => 'link_carousel_feature',
        '#uri' => $file->getFileUri(),
        '#attributes' => [
          'alt' => $value['image_alt'] ?? '',
          'loading' => 'eager',
          'fetchpriority' => 'high',
        ],
      ] : NULL,
      'thumbnail' => $file ? [
        '#theme' => 'image_style',
        '#style_name' => 'link_carousel_feature_thumbnail',
        '#uri' => $file->getFileUri(),
        '#alt' => '',
        '#attributes' => ['loading' => 'lazy'],
      ] : NULL,
    ];
  }

  /**
   * Formats an already resolved public link without repeating access checks.
   */
  private function buildFeatureLink(Url $url, $label, bool $button = FALSE): array {
    $attributes = $url->getOption('attributes') ?? [];
    if ($button) {
      $attributes['class'][] = 'cd-button';
      $attributes['class'][] = $url->isExternal() ? 'external' : 'read-more';
    }
    if ($url->isExternal()) {
      $attributes['target'] = '_blank';
      $attributes['rel'] = 'noopener noreferrer';
    }
    $url->setOption('attributes', $attributes);
    return Link::fromTextAndUrl($label, $url)->toRenderable();
  }

  /**
   * Shares donation completeness and destination rules with the editor.
   *
   * @return array{url: ?\Drupal\Core\Url, issues: array<string, \Drupal\Core\StringTranslation\TranslatableMarkup[]>}
   *   A public URL and issues grouped by the donation field needing attention.
   */
  private function getDonationAvailability(array $settings, ?CacheableMetadata $cache = NULL): array {
    $availability = LinkCarouselLink::resolve((string) ($settings['url'] ?? ''), $cache);
    // Keep issues tied to their fields for non-blocking editorial feedback.
    $availability['issues'] = $availability['issues'] ? ['url' => $availability['issues']] : [];
    $required = [
      'title' => $this->t('Add a title for the donation panel.'),
      'description' => $this->t('Add a description for the donation panel.'),
      'button_label' => $this->t('Add a button label for the donation panel.'),
    ];
    foreach ($required as $key => $message) {
      if (trim((string) ($settings[$key] ?? '')) === '') {
        $availability['issues'][$key] = [$message];
      }
    }
    return $availability;
  }

  /**
   * Returns default news and donation configuration.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'links' => [
        'items' => NULL,
        // NULL uses the default display limit; zero is an explicit cutoff.
        'display_end_weight' => NULL,
      ],
      // Resolve donation defaults on read so missing and explicit values remain
      // distinguishable when older item lists contain a donation slide.
      'donation' => [],
    ];
  }

  /**
   * Returns default content for the independent donation panel.
   */
  private function getDonationDefaults(): array {
    return [
      'enabled' => TRUE,
      'title' => 'Donate to relief organizations',
      'description' => 'Conflict and violence have worsened ongoing crises while at the same time, critical aid financing and spending have been disrupted. With your help, relief organizations can reach crisis-affected people.',
      'url' => 'https://crisisrelief.un.org/donate',
      'button_label' => 'Donate here',
      'source_items' => [],
    ];
  }

  /**
   * Reads older item lists without rewriting saved layouts or revisions.
   */
  public function getBlockConfig() {
    return $this->getEffectiveConfig(parent::getBlockConfig());
  }

  /**
   * Resolves legacy item storage and donation defaults without persisting them.
   */
  private function getEffectiveConfig(array $conf): array {
    $donation = $conf['donation'] ?? [];
    $source_items = $donation['source_items'] ?? [];
    $items = $conf['links']['items'] ?? NULL;
    $legacy_defaults = [];
    if ($items === NULL) {
      // Multistep form state contains only subform keys, not legacy root items.
      $items = $conf['items'] ?? $this->configuration['hpc']['items'] ?? [];
      $donation_paths = ['/donate', '/en/donate'];
      foreach ($items as $key => $item) {
        $value = $item['config']['value'] ?? [];
        $url = parse_url(trim($value['url'] ?? ''));
        $is_donation = is_array($url) && strtolower($url['scheme'] ?? '') === 'https' && strtolower($url['host'] ?? '') === 'crisisrelief.un.org' && in_array(rtrim($url['path'] ?? '', '/'), $donation_paths, TRUE);
        if (!$is_donation) {
          continue;
        }
        // Original donation slides must not consume news positions. Keep their
        // complete records, including images, when the element is later saved.
        $source_items[$key] ??= $item;
        $legacy_defaults += array_filter([
          'title' => $item['config']['label'] ?? '',
          'description' => $value['description'] ?? '',
          'url' => $value['url'] ?? '',
          'button_label' => $value['button_label'] ?? '',
        ], static fn($value) => $value !== '' && $value !== NULL);
        unset($items[$key]);
      }
    }
    $conf['links']['items'] = $items;
    $conf['links']['display_end_weight'] = isset($conf['links']['display_end_weight']) ? (int) $conf['links']['display_end_weight'] : NULL;
    // Array union preserves explicit disabling and deliberately cleared fields.
    $conf['donation'] = $donation + $legacy_defaults + $this->getDonationDefaults();
    $conf['donation']['source_items'] = $source_items;
    unset($conf['items']);
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'links';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'links';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return static::metadata()->configForms;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Initialize both subforms from saved settings, not pending child input.
    // Rendering only reads this compatibility view; an editor save persists it.
    $this->configuration['hpc'] = $this->getEffectiveConfig($this->configuration['hpc']);
    return parent::blockForm($form, $form_state);
  }

  /**
   * Builds the existing item editor with order-based placement indicators.
   */
  public function linksForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'items');
    $default_value ??= $this->getBlockConfig()['links']['items'];
    $parents = $form['#parents'] ?? ['links'];
    $container = ['#parents' => array_merge($parents, ['items'])];
    if (ConfigurationContainer::has($container, $form_state, 'items')) {
      $default_value = ConfigurationContainer::get($container, $form_state, 'items');
    }
    $end_weight = ConfigurationContainer::has($container, $form_state, 'display_end_weight')
      ? ConfigurationContainer::get($container, $form_state, 'display_end_weight')
      : $this->getBlockConfig()['links']['display_end_weight'];
    $form['display_end_weight'] = [
      '#type' => 'value',
      '#value' => $end_weight,
    ];
    $element_info = \Drupal::service('element_info')->getInfo('configuration_container');
    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Links'),
      '#title_display' => 'invisible',
      '#description' => $this->t('<strong>Items are displayed in list order.</strong> Above the separator, the first valid item is <em>Main news</em>, followed by up to @count <em>Featured items</em>. Invalid items are skipped.<br>Drag the separator to choose where displayed news ends, or move links across it. Items below it remain saved but are not displayed.', ['@count' => self::MAX_FEATURED_ITEMS]),
      '#item_type_label' => $this->t('News item'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'placement' => $this->t('Placement'),
          'label' => $this->t('Title'),
          'url_string' => $this->t('URL'),
          'thumbnail' => $this->t('Thumbnail'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#display_end_weight' => $end_weight,
      // The separator's weight must receive normal Form API input processing.
      '#process' => array_merge($element_info['#process'], [[CarouselItem::class, 'decoratePlacementTable']]),
      '#element_validate' => [[static::class, 'captureLinkOrder']],
      '#element_submit' => [[static::class, 'submitLinkItems']],
    ];
    $form['#attached']['library'][] = 'ghi_blocks/link_carousel.editor';
    return $form;
  }

  /**
   * Places newly added news without changing the existing displayed selection.
   */
  public static function submitLinkItems(array &$element, FormStateInterface $form_state, array $form): void {
    $adding = ConfigurationContainer::get($element, $form_state, 'mode') === 'add_item'
      && $form_state->getTriggeringElement()['#parents'] === ($element['item_config']['actions']['submit_item']['#parents'] ?? NULL);
    $previous_items = $adding ? ConfigurationContainer::get($element, $form_state, 'items') ?? [] : [];
    // The generic handler resets the mode, so capture add intent beforehand.
    ConfigurationContainer::elementSubmit($element, $form_state, $form);
    if (!$adding) {
      return;
    }

    $items = ConfigurationContainer::get($element, $form_state, 'items');
    $previous_ids = array_fill_keys(array_column($previous_items, 'id'), TRUE);
    $end_weight = ConfigurationContainer::get($element, $form_state, 'display_end_weight');
    foreach ($items as &$item) {
      if (isset($previous_ids[$item['id']])) {
        continue;
      }
      // Even an incomplete first draft belongs above the boundary, so repairing
      // it later can display it without moving the saved physical selection.
      $item['weight'] = $previous_items ? max($end_weight, ...array_column($previous_items, 'weight')) + 1 : 0;
      if (!$previous_items) {
        $end_weight = 1;
      }
    }
    unset($item);
    self::storeLinkItems($element, $form_state, $items, $end_weight);
  }

  /**
   * Captures tabledrag weights for Update, tab navigation, and preview alike.
   */
  public static function captureLinkOrder(array &$element, FormStateInterface $form_state): void {
    // Item-editing modes submit nested configuration, not row weights.
    if (!isset($element['summary_table'])) {
      return;
    }
    $trigger_parents = $form_state->getTriggeringElement()['#parents'] ?? [];
    $save_order = $trigger_parents === ($element['actions']['save_order']['#parents'] ?? NULL);
    $rows = $form_state->getValue(array_merge($element['#parents'], ['summary_table']));
    // Other element callbacks may already have cleaned the processed values.
    $rows ??= NestedArray::getValue($form_state->getUserInput(), array_merge($element['#parents'], ['summary_table']));
    $items = ConfigurationContainer::get($element, $form_state, 'items') ?? [];
    $order = [];
    foreach (Element::children($element['summary_table'], TRUE) as $key) {
      $row = $element['summary_table'][$key];
      $order[$key] = [
        'weight' => (int) ($rows[$key]['weight'] ?? $row['#weight']),
        'available' => ($row['#attributes']['data-link-carousel-available'] ?? '0') === '1',
      ];
    }
    $original_order = array_keys($order);
    ArrayHelper::sortArrayByNumericKey($order, 'weight', SORT_ASC);
    $keys = array_keys($order);
    if ($keys !== $original_order) {
      // Check drag limits when the relative row order changes. Keep the saved
      // cutoff when only item availability has changed.
      $position = array_search(self::DISPLAY_END_ROW, $keys, TRUE);
      $available_positions = array_keys(array_filter(array_values($order), static fn(array $row): bool => $row['available']));
      if ($available_positions) {
        $minimum = reset($available_positions) + 1;
        $maximum = $available_positions[self::MAX_FEATURED_ITEMS + 1] ?? count($keys);
        if ($position < $minimum || $position > $maximum) {
          array_splice($keys, $position, 1);
          // Removing a separator above Main news shifts the insertion slot
          // left. At the upper limit, insert before the first overflow item.
          $position = $position < $minimum ? $minimum - 1 : $maximum;
          array_splice($keys, $position, 0, [self::DISPLAY_END_ROW]);
        }
      }
    }
    // Give the separator its own slot in the shared weight sequence. A count
    // of valid items would move the cutoff when availability later changes.
    $weights = array_flip($keys);
    foreach ($items as &$item) {
      if (isset($weights[$item['id']])) {
        $item['weight'] = $weights[$item['id']];
      }
    }
    unset($item);
    self::storeLinkItems($element, $form_state, $items, $weights[self::DISPLAY_END_ROW]);
    if ($save_order) {
      // Generic Save order expects table rows, not records. Never pass it the
      // separator or unknown raw-input IDs: it only handles actual items.
      $saved_rows = [];
      foreach ($items as $item) {
        $saved_rows[$item['id']] = [
          'id' => $item['id'],
          'weight' => $item['weight'],
          'pid' => $item['pid'] ?? NULL,
        ];
      }
      $form_state->setValue(array_merge($element['#parents'], ['summary_table']), $saved_rows);
    }
  }

  /**
   * Keeps news and its physical boundary synchronized across editor actions.
   */
  private static function storeLinkItems(array $element, FormStateInterface $form_state, array $items, int $end_weight): void {
    $parents = array_slice($element['#parents'], 0, -1);
    $form_state->setValue(array_merge($parents, ['display_end_weight']), $end_weight);
    // Child upload AJAX rebuilds may omit the sibling value element. Preserve
    // an independent anchor in container state, which is never saved as news.
    ConfigurationContainer::set($element, $form_state, 'display_end_weight', $end_weight);
    ConfigurationContainer::setItems($element, $form_state, $items);
    $settings = $form_state->getValue($parents);
    $key = reset($parents);
    $form_state->setTemporaryValue($key, $settings);
    $form_state->set(['storage', $key], $settings);
  }

  /**
   * Builds the independent donation controls.
   */
  public function donationForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getBlockConfig()['donation'];
    $cache = new CacheableMetadata();
    $availability = $this->getDonationAvailability($settings, $cache);
    $form['caption'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('<strong>Donation panel</strong><br>Highlighted fields need attention before the panel can be displayed. You can still save incomplete settings.'),
      '#attributes' => ['class' => ['link-carousel-editor__caption']],
      '#weight' => -10,
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the donation call to action'),
      '#default_value' => $settings['enabled'] ?? FALSE,
    ];
    $fields = [
      'title' => $this->t('Title'),
      'description' => $this->t('Description'),
      'url' => $this->t('URL'),
      'button_label' => $this->t('Button label'),
    ];
    foreach ($fields as $key => $label) {
      $issues = $availability['issues'][$key] ?? [];
      $form[$key] = [
        '#type' => $key === 'description' ? 'textarea' : 'textfield',
        '#title' => $label,
        '#default_value' => $settings[$key] ?? '',
        // Keep a permanent description target, even for initially valid fields.
        '#description' => new FormattableMarkup('<span data-link-carousel-donation-feedback aria-live="polite">@feedback</span>', ['@feedback' => $this->getDonationFeedback($issues)]),
        '#wrapper_attributes' => ['data-link-carousel-donation-field' => $key],
      ];
      if ($issues) {
        $form[$key]['#wrapper_attributes']['class'][] = 'link-carousel-editor__field--unavailable';
      }
    }
    $form['url']['#description'] .= $this->t('Enter an HTTP or HTTPS URL, an internal path, or an entity URI.');
    // Core disables the AJAX trigger in flight. Keep it separate from editable
    // fields so an immediate Update still includes all of their current values.
    $feedback_id = Html::getUniqueId('link-carousel-donation-feedback');
    $form['feedback'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#parents' => ['link_carousel_donation_feedback'],
      // Hidden elements do not copy #id to HTML attributes during pre-render.
      '#id' => $feedback_id,
      '#attributes' => [
        'id' => $feedback_id,
        'data-link-carousel-donation-refresh' => '',
      ],
      '#ajax' => [
        'callback' => [$this, 'updateDonationFeedback'],
        'event' => 'linkCarouselDonationFeedback',
        'progress' => ['type' => 'none'],
        'disable-refocus' => TRUE,
        'refocus-blur' => FALSE,
      ],
    ];
    $form_state->addCleanValueKey(['link_carousel_donation_feedback']);
    $form['source_items'] = [
      '#type' => 'value',
      '#value' => $settings['source_items'] ?? [],
    ];
    $cache->applyTo($form);
    $form['#attached']['library'][] = 'ghi_blocks/link_carousel.editor';
    return $form;
  }

  /**
   * Updates feedback without replacing inputs or moving the editor's focus.
   */
  public function updateDonationFeedback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $key = $form_state->getTriggeringElement()['#value'] ?? '';
    $fields = ['title', 'description', 'url', 'button_label'];
    if ($form_state->get('current_subform') !== 'donation' || !in_array($key, $fields, TRUE)) {
      return $response;
    }
    $availability = $this->getDonationAvailability($form_state->getValue('donation', []));
    $issues = $availability['issues'][$key] ?? [];
    $selector = '#' . $form['#id'] . ' [data-link-carousel-donation-field="' . $key . '"]';
    $response->addCommand(new InvokeCommand($selector, 'toggleClass', [
      'link-carousel-editor__field--unavailable',
      (bool) $issues,
    ]));
    // Update only the requested field; other edits may still be queued.
    $response->addCommand(new HtmlCommand($selector . ' [data-link-carousel-donation-feedback]', ['#markup' => $this->getDonationFeedback($issues)]));
    return $response;
  }

  /**
   * Formats non-blocking feedback for initial display and AJAX refreshes alike.
   */
  private function getDonationFeedback(array $issues): TranslatableMarkup|string {
    return $issues ? $this->t('<strong>Not available:</strong> @reason', ['@reason' => implode(' ', array_map('strval', $issues))]) : '';
  }

  /**
   * Normalizes valid destinations without blocking incomplete editorial drafts.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $action = $form_state->getTriggeringElement()['#parents'] ?? [];
    if ($action && $action !== ['actions', 'submit'] && end($action) !== 'preview') {
      return;
    }
    // Cached AJAX callbacks can retain an older state. Normalize the values
    // submitted now, even when an earlier feedback request is still running.
    $this->setFormState($form_state);
    $conf = $this->getBlockConfig();
    foreach ($conf['links']['items'] ?? [] as $key => $item) {
      $uri = LinkCarouselLink::normalize($item['config']['value']['url'] ?? '');
      if ($uri !== NULL) {
        $conf['links']['items'][$key]['config']['value']['url'] = $uri;
      }
    }
    $uri = LinkCarouselLink::normalize($conf['donation']['url'] ?? '');
    if ($uri !== NULL) {
      $conf['donation']['url'] = $uri;
    }
    foreach (['links', 'donation'] as $key) {
      if (isset($conf[$key])) {
        $form_state->setValue($key, $conf[$key]);
        $form_state->setTemporaryValue($key, $conf[$key]);
        $form_state->set(['storage', $key], $conf[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityInterface $entity, $uuid) {
    $submitted_files = $this->getFiles();
    $stored_files = $this->getStoredFiles($entity, $uuid);
    if (empty($submitted_files) && empty($stored_files)) {
      return;
    }
    $this->persistFiles($submitted_files, $entity, $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(EntityInterface $entity, $uuid) {
    $files = $this->getFiles();
    $this->cleanupFiles($files, $entity, $uuid);
  }

  /**
   * Get the files included in this blocks configuration.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file objects configured for this block.
   */
  private function getFiles() {
    $conf = $this->getBlockConfig();
    $fids = [];
    foreach (array_merge($conf['links']['items'], $conf['donation']['source_items']) as $item) {
      if (($item['item_type'] ?? NULL) !== 'carousel_item') {
        continue;
      }
      $fids = array_merge($fids, $item['config']['value']['image'] ?? []);
    }
    return File::loadMultiple(array_unique($fids));
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'carousel_item' => [],
    ];
    return $item_types;
  }

}
