<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Helpers\LinkCarouselLink;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;

/**
 * Provides a carousel item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'carousel_item',
  label: new TranslatableMarkup('Carousel item'),
  description: new TranslatableMarkup('This item displays an image with text to be used as part of a carousel.'),
)]
class CarouselItem extends ConfigurationContainerItemPluginBase {

  use CustomLinkTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#title'] = $this->t('Title');

    $element['value'] = [
      '#type' => 'carousel_item',
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];

    $element['#element_validate'] = [
      [static::class, 'validateElement'],
    ];
    return $element;
  }

  /**
   * Normalizes valid URLs without blocking unavailable news items.
   *
   * @param array $element
   *   The element built in ::buildForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The complete form that contains the element.
   */
  public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form) {
    $values = $form_state->getValue($element['#parents'])['value'] ?? [];
    $url = (string) ($values['url'] ?? '');
    $uri = LinkCarouselLink::normalize($url);
    if ($uri !== NULL && $uri !== $url) {
      $form_state->setValue(array_merge($element['#parents'], ['value', 'url']), $uri);
    }
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description string.
   */
  public function getDescription() {
    return $this->config['value']['description'] ?? '';
  }

  /**
   * Provides the placement column, filled from row order after table building.
   */
  public function getPlacement() {
    return '';
  }

  /**
   * Adds placement indicators and the draggable display boundary.
   *
   * @param array $element
   *   The configuration container before its children are processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The decorated container.
   */
  public static function decoratePlacementTable(array $element, FormStateInterface $form_state): array {
    if (!isset($element['summary_table'])) {
      return $element;
    }
    $table = &$element['summary_table'];
    $table['#attributes']['data-link-carousel-placements'] = '';
    // Keep client-side placement limits aligned with the public renderer.
    $table['#attributes']['data-link-carousel-max-items'] = LinkCarousel::MAX_FEATURED_ITEMS + 1;
    $end_weight = $element['#display_end_weight'] ?? NULL;
    unset($table['#header']['issues']);
    $index = 0;
    $keys = Element::children($table, TRUE);
    $end_position = count($keys);
    $boundary_found = FALSE;
    foreach ($keys as $position => $key) {
      unset($table[$key]['issues']);
      if (!isset($table[$key]['placement'])) {
        continue;
      }
      $issues = $table[$key]['#configuration_errors'];
      $available = !$issues;
      if (!$boundary_found && $end_weight !== NULL && (int) $table[$key]['#weight'] >= $end_weight) {
        $end_position = $position;
        $boundary_found = TRUE;
      }
      $role = 'unavailable';
      $label = t('Not available');
      if ($available) {
        $displayed = !$boundary_found && $index <= LinkCarousel::MAX_FEATURED_ITEMS;
        $role = $displayed ? ($index === 0 ? 'main' : 'featured') : 'hidden';
        $label = $displayed ? ($index === 0 ? t('Main news') : t('Featured @number', ['@number' => $index])) : t('Not displayed');
        $index++;
        if ($end_weight === NULL && !$boundary_found && $index === LinkCarousel::MAX_FEATURED_ITEMS + 1) {
          $end_position = $position + 1;
          $boundary_found = TRUE;
        }
      }
      $table[$key]['#attributes']['data-link-carousel-available'] = $available ? '1' : '0';
      $table[$key]['#attributes']['class'][] = 'link-carousel-editor__row--' . $role;
      $placement_parents = array_merge($element['#parents'], ['summary_table', $key, 'placement']);
      $placement_array_parents = array_merge($element['#array_parents'], ['summary_table', $key, 'placement']);
      $table[$key]['placement'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['link-carousel-editor__placement'],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $label,
          '#attributes' => [
            'data-link-carousel-placement' => '',
          ],
          '#parents' => array_merge($placement_parents, ['label']),
          '#array_parents' => array_merge($placement_array_parents, ['label']),
        ],
      ] + $table[$key]['placement'];
      unset($table[$key]['placement']['tooltip']);
      if (!$available) {
        $reasons = implode(' ', array_map('strval', $issues));
        $table[$key]['placement']['tooltip'] = [
          '#theme' => 'hpc_tooltip',
          '#tooltip' => $reasons,
          '#tag' => 'span',
          '#class' => 'link-carousel-editor__availability-tooltip',
          '#parents' => array_merge($placement_parents, ['tooltip']),
          '#array_parents' => array_merge($placement_array_parents, ['tooltip']),
          '#tag_content' => [
            'icon' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => 'ⓘ',
              '#attributes' => ['aria-hidden' => 'true'],
            ],
            'label' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              // Interactive tooltips do not add aria-describedby automatically.
              // Keep the reasons in the focused trigger's accessible text too.
              '#value' => t('Why this item is not available: @reasons', ['@reasons' => $reasons]),
              '#attributes' => ['class' => ['visually-hidden']],
            ],
          ],
        ];
      }
    }
    $separator = [
      '#attributes' => [
        'class' => ['draggable', 'tabledrag-leaf', 'link-carousel-editor__separator'],
        'data-link-carousel-display-end' => '',
      ],
      'placement' => [
        '#type' => 'container',
        '#wrapper_attributes' => ['colspan' => 4],
        '#attributes' => ['class' => ['link-carousel-editor__boundary']],
        'label' => ['#markup' => t('Displayed news ends here')],
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => t('Display boundary weight'),
        '#title_display' => 'invisible',
        '#attributes' => ['class' => ['row-weight']],
      ],
      'id' => [
        '#markup' => '',
        '#wrapper_attributes' => ['class' => ['tabledrag-hide']],
      ],
      'operations' => ['#markup' => ''],
    ];
    if ($end_position === 0) {
      // Core discovers hidden weight columns from the first row's cell index,
      // before accounting for colspans. Keep its column discovery aligned when
      // an empty or previously invalidated selection puts the separator first.
      unset($separator['placement']['#wrapper_attributes']['colspan']);
      $separator = array_slice($separator, 0, 2, TRUE)
        + array_fill_keys(['label', 'url_string', 'thumbnail'], ['#markup' => ''])
        + array_slice($separator, 2, NULL, TRUE);
    }
    array_splice($keys, $end_position, 0, [LinkCarousel::DISPLAY_END_ROW]);
    $rows = [];
    foreach ($keys as $position => $key) {
      $rows[$key] = $key === LinkCarousel::DISPLAY_END_ROW ? $separator : $table[$key];
      // A unique shared order keeps the separator between items even when
      // several saved news rows originally had the same weight.
      $rows[$key]['#weight'] = $position;
      $rows[$key]['weight']['#default_value'] = $position;
      $rows[$key]['weight']['#delta'] = count($keys);
      unset($table[$key]);
    }
    $table += $rows;
    unset($table['#sorted']);
    $element['placement_feedback'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '',
      '#attributes' => [
        'class' => ['link-carousel-editor__placement-feedback'],
        'data-link-carousel-placement-feedback' => '',
        'aria-live' => 'polite',
      ],
      '#weight' => -1,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    return $this->getAvailabilityIssues();
  }

  /**
   * Gets reasons why this item cannot currently be displayed publicly.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional metadata collecting file, destination and access dependencies.
   * @param array|null $resolved_link
   *   Optional result from LinkCarouselLink::resolve(), with destination cache
   *   dependencies already collected. Reuses the renderer's resolved URL.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The availability issues, or an empty array for available items.
   */
  public function getAvailabilityIssues(?CacheableMetadata $cache = NULL, ?array $resolved_link = NULL): array {
    $cache ??= new CacheableMetadata();
    $errors = [];
    if (trim((string) ($this->config['label'] ?? '')) === '') {
      $errors[] = $this->t('Add a title for this item.');
    }
    $file = $this->getImage();
    if ($file) {
      $cache->addCacheableDependency($file);
    }
    else {
      $cache->addCacheTags(['file_list']);
      $errors[] = $this->t('Add an image for this item.');
    }
    $resolved_link ??= LinkCarouselLink::resolve((string) ($this->config['value']['url'] ?? ''), $cache);
    return array_merge($errors, $resolved_link['issues']);
  }

  /**
   * Returns the item's optional publication date.
   */
  public function getPublicationDate(): string {
    return $this->config['value']['publication_date'] ?? '';
  }

  /**
   * Get the image.
   *
   * @return \Drupal\file\Entity\File|null
   *   A file object for the image file or NULL.
   */
  public function getImage() {
    $image_ids = $this->config['value']['image'] ?? [];
    return !empty($image_ids) ? File::load(reset($image_ids)) : NULL;
  }

  /**
   * Get the image credit.
   *
   * @return string|null
   *   The image credit, or NULL if none is set.
   */
  public function getImageCredit() {
    return $this->config['value']['image_credit'] ?? NULL;
  }

  /**
   * Get the image caption.
   *
   * @return \Drupal\Component\Render\FormattableMarkup|string|null
   *   Caption and credit text, combined when both are set.
   */
  public function getImageCaption() {
    $credit = $this->getImageCredit();
    $caption = $this->config['value']['image_caption'] ?? NULL;
    if ($caption && $credit) {
      return new FormattableMarkup('@text <span class="credits">@credits</span>', [
        '@text' => $caption,
        '@credits' => $credit,
      ]);
    }
    return $caption ?: $credit;
  }

  /**
   * Get the link.
   *
   * @return \Drupal\Core\Link|null
   *   A link object or NULL.
   */
  public function getLink() {
    if (empty($this->config['value']['url'])) {
      return NULL;
    }
    $label = $this->getButtonLabel() ?? $this->t('Read more');
    return $this->getLinkFromUri($this->config['value']['url'], $label);
  }

  /**
   * Get the url.
   *
   * @return \Drupal\Core\Url|null
   *   A url object or NULL.
   */
  public function getUrl() {
    try {
      $link = $this->getLink();
      return $link?->getUrl();
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
  }

  /**
   * Get the url as a string.
   *
   * @return string|null
   *   The url as a string or NULL.
   */
  public function getUrlString() {
    // Invalid retained rows must remain editable instead of breaking the list.
    $uri = LinkCarouselLink::normalize((string) ($this->config['value']['url'] ?? ''));
    if ($uri === NULL) {
      return NULL;
    }
    return Url::fromUri($uri)->toString();
  }

  /**
   * Get the button label.
   *
   * @return string|null
   *   The button label.
   */
  public function getButtonLabel() {
    return (string) ($this->config['value']['button_label'] ?? NULL) ?: NULL;
  }

  /**
   * Get the image as thumbnail.
   *
   * @return array|null
   *   The thumbnail render array, or NULL if the image is missing.
   */
  public function getThumbnail() {
    $image = $this->getImage();
    if (!$image) {
      return NULL;
    }
    return [
      '#theme' => 'image_style',
      '#style_name' => 'thumbnail',
      '#uri' => $image->getFileUri(),
    ];
  }

}
