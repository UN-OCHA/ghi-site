<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

/**
 * Adds non-blocking editorial length guidance to plain-text descriptions.
 */
final class DescriptionLengthFeedback {

  public const RECOMMENDED_LENGTH = 200;

  public const LONG_LENGTH = 350;

  /**
   * Adds live feedback without imposing a maximum input length.
   *
   * @param array $element
   *   A plain-text textarea element.
   *
   * @return array
   *   The element with advisory length feedback enabled.
   */
  public static function apply(array $element): array {
    $callback = [self::class, 'afterBuild'];
    if (!in_array($callback, $element['#after_build'] ?? [], TRUE)) {
      $element['#after_build'][] = $callback;
      // FormBuilder replaces this attribute when it adds the help association.
      $element['#description_length_describedby'] = $element['#attributes']['aria-describedby'] ?? [];
    }
    return $element;
  }

  /**
   * Adds server-rendered feedback using the processed input and field ID.
   *
   * @param array $element
   *   The built textarea element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The element with feedback, preserving its existing description.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    // Textareas normalize line endings in the browser. Count raw Unicode code
    // points on both sides so emoji and literal markup have matching totals.
    $value = (string) ($element['#value'] ?? $element['#default_value'] ?? '');
    $length = mb_strlen(str_replace(["\r\n", "\r"], "\n", $value));
    $state = match (TRUE) {
      $length > self::LONG_LENGTH => 'very-long',
      $length > self::RECOMMENDED_LENGTH => 'long',
      default => 'recommended',
    };
    $messages = [
      'recommended' => (string) t('Within the recommended length.'),
      'long' => (string) t('Long description. Consider shortening it.'),
      'very-long' => (string) t('Above the recommended maximum of @long characters. Consider shortening it.', ['@long' => self::LONG_LENGTH]),
    ];
    $feedback_id = $element['#id'] . '--length-feedback';
    $element['#attributes']['data-description-length-feedback'] = $feedback_id;
    $element['#attributes']['data-description-length-recommended'] = self::RECOMMENDED_LENGTH;
    $element['#attributes']['data-description-length-long'] = self::LONG_LENGTH;
    $element['#attributes']['data-description-length-state'] = $state;

    $description = $element['#description'] ?? '';
    $element['#description'] = [
      '#type' => 'component',
      '#component' => 'ghi_blocks:description-length-feedback',
      '#props' => [
        'feedback_id' => $feedback_id,
        'state' => $state,
        'count_label' => (string) new PluralTranslatableMarkup($length, '@count character', '@count characters'),
        'messages' => $messages,
        'guidance' => (string) t('Aim for @recommended characters or fewer, with a recommended maximum of @long. Orange indicates a long description; red indicates that it exceeds the recommended maximum. You can still save.', [
          '@recommended' => self::RECOMMENDED_LENGTH,
          '@long' => self::LONG_LENGTH,
        ]),
      ],
      '#slots' => [
        'original_help' => is_array($description) ? $description : ['#markup' => $description],
      ],
    ];

    $description_ids = array_merge((array) ($element['#description_length_describedby'] ?? []), (array) ($element['#attributes']['aria-describedby'] ?? []), [$element['#id'] . '--description']);
    $description_ids = preg_split('/\s+/', implode(' ', $description_ids), -1, PREG_SPLIT_NO_EMPTY);
    $element['#attributes']['aria-describedby'] = implode(' ', array_unique($description_ids));
    return $element;
  }

}
