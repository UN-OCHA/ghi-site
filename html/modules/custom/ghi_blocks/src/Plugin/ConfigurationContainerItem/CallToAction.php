<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Helpers\DescriptionLengthFeedback;
use Drupal\ghi_blocks\Helpers\LinkCarouselLink;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;

/**
 * Provides editable call-to-action records with non-blocking feedback.
 */
#[ConfigurationContainerItem(
  id: 'call_to_action',
  label: new TranslatableMarkup('Call to action'),
  description: new TranslatableMarkup('A title, description and linked button.'),
)]
class CallToAction extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $complete_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    $submitted = $complete_state->getValue($element['#parents'], []);
    $settings = $submitted + $this->config;
    $settings['value'] = ($submitted['value'] ?? []) + ($this->config['value'] ?? []);
    $cache = new CacheableMetadata();
    $availability = self::getAvailability($settings, $cache);

    $element['#tree'] = TRUE;
    $element['#cta_feedback_wrapper_id'] = $this->wrapperId;
    $element['caption'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Highlighted fields need attention before this call to action can be displayed. You can still save incomplete settings.'),
      '#attributes' => ['class' => ['link-carousel-editor__caption']],
      '#weight' => -10,
    ];
    $element['label']['#title'] = $this->t('Title');
    // The parent helper falls back for empty input; a deliberately cleared
    // draft title must instead stay empty and receive its own feedback.
    $element['label']['#default_value'] = $settings['label'] ?? '';
    $element['value'] = ['#type' => 'container', '#tree' => TRUE];
    $element['value']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $settings['value']['description'] ?? '',
    ];
    $element['value']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => $settings['value']['url'] ?? '',
      '#maxlength' => 2048,
    ];
    $element['value']['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $settings['value']['button_label'] ?? '',
    ];
    $element['value']['show_heart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show heart icon'),
      '#default_value' => !empty($settings['value']['show_heart']),
    ];
    foreach (['label', 'description', 'url', 'button_label'] as $key) {
      $field = &$element;
      if ($key !== 'label') {
        $field = &$element['value'];
      }
      $issues = $availability['issues'][$key] ?? [];
      $field[$key]['#description'] = new FormattableMarkup('<span data-link-carousel-cta-feedback aria-live="polite">@feedback</span>', ['@feedback' => self::getFeedback($issues)]);
      $field[$key]['#wrapper_attributes']['data-link-carousel-cta-field'] = $key;
      if ($issues) {
        $field[$key]['#wrapper_attributes']['class'][] = 'link-carousel-editor__field--unavailable';
      }
      unset($field);
    }
    $element['value']['url']['#description'] .= $this->t('Enter an HTTP or HTTPS URL, an internal path, or an entity URI.');
    $element['value']['description'] = DescriptionLengthFeedback::apply($element['value']['description']);

    // Only this hidden trigger is disabled during feedback requests. Keep it
    // outside the record so an immediate save includes every editable value.
    $feedback_id = Html::getUniqueId('link-carousel-cta-feedback');
    $element['feedback'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#parents' => ['link_carousel_cta_feedback'],
      '#id' => $feedback_id,
      '#attributes' => ['id' => $feedback_id, 'data-link-carousel-cta-refresh' => ''],
      '#ajax' => [
        'callback' => [static::class, 'updateFeedback'],
        'event' => 'linkCarouselCtaFeedback',
        'progress' => ['type' => 'none'],
        'disable-refocus' => TRUE,
        'refocus-blur' => FALSE,
      ],
    ];
    $complete_state->addCleanValueKey(['link_carousel_cta_feedback']);
    $element['#element_validate'][] = [static::class, 'validateElement'];
    $element['#attached']['library'][] = 'ghi_blocks/link_carousel.editor';
    $cache->applyTo($element);
    return $element;
  }

  /**
   * Returns public availability and reasons grouped by their editable field.
   *
   * @param array $config
   *   The item's label and value configuration.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional destination and anonymous-access cache metadata.
   *
   * @return array{url: ?\Drupal\Core\Url, issues: array<string, \Drupal\Core\StringTranslation\TranslatableMarkup[]>}
   *   A public URL and non-blocking availability explanations.
   */
  public static function getAvailability(array $config, ?CacheableMetadata $cache = NULL): array {
    $availability = LinkCarouselLink::resolve((string) ($config['value']['url'] ?? ''), $cache);
    $availability['issues'] = $availability['issues'] ? ['url' => $availability['issues']] : [];
    $required = [
      'label' => new TranslatableMarkup('Add a title for the call to action.'),
      'description' => new TranslatableMarkup('Add a description for the call to action.'),
      'button_label' => new TranslatableMarkup('Add a button label for the call to action.'),
    ];
    foreach ($required as $key => $message) {
      $value = $key === 'label' ? ($config['label'] ?? '') : ($config['value'][$key] ?? '');
      if (trim((string) $value) === '') {
        $availability['issues'][$key] = [$message];
      }
    }
    return $availability;
  }

  /**
   * Provides a status cell filled by the component's table decorator.
   */
  public function getStatus(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    return array_merge([], ...array_values(self::getAvailability($this->config)['issues']));
  }

  /**
   * Normalizes supported URLs without rejecting unfinished records.
   */
  public static function validateElement(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $complete_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    if (($complete_state->getTriggeringElement()['#parents'] ?? []) === ['link_carousel_cta_feedback']) {
      // Feedback is read-only. A late response must not replace newer editor
      // state or point the browser at an uncached, newly generated build ID.
      $complete_state->disableCache();
      $copy = $complete_state->getRebuildInfo()['copy'] ?? [];
      $copy['#build_id'] = TRUE;
      $complete_state->addRebuildInfo('copy', $copy);
    }
    $config = $complete_state->getValue($element['#parents'], []);
    $uri = LinkCarouselLink::normalize((string) ($config['value']['url'] ?? ''));
    if ($uri !== NULL) {
      $config['value']['url'] = $uri;
    }
    $config['value']['show_heart'] = !empty($config['value']['show_heart']);
    $complete_state->setValue($element['#parents'], $config);
  }

  /**
   * Refreshes one field's feedback without replacing or saving editable input.
   */
  public static function updateFeedback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $trigger = $form_state->getTriggeringElement();
    $key = $trigger['#value'] ?? '';
    if (!in_array($key, ['label', 'description', 'url', 'button_label'], TRUE)) {
      return $response;
    }
    // Value parents differ from array parents inside configuration containers.
    // Locate the current editor from the processed trigger, not a fixed path.
    $editor = NestedArray::getValue($form, array_slice($trigger['#array_parents'] ?? [], 0, -1));
    if (empty($editor['#cta_feedback_wrapper_id']) || empty($editor['#parents'])) {
      return $response;
    }
    $complete_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    $availability = self::getAvailability($complete_state->getValue($editor['#parents'], []));
    $issues = $availability['issues'][$key] ?? [];
    // Editor actions change the form build ID. Ignore feedback that arrives
    // after another editor has replaced the one that requested it.
    $selector = implode(' ', [
      '#' . $form['#id'] . ':has(input[name="form_build_id"][value="' . $form['#build_id'] . '"])',
      '#' . $editor['#cta_feedback_wrapper_id'],
      '[data-link-carousel-cta-field="' . $key . '"]',
    ]);
    $response->addCommand(new InvokeCommand($selector, 'toggleClass', [
      'link-carousel-editor__field--unavailable',
      (bool) $issues,
    ]));
    $response->addCommand(new HtmlCommand($selector . ' [data-link-carousel-cta-feedback]', ['#markup' => self::getFeedback($issues)]));
    return $response;
  }

  /**
   * Formats escaped explanations for both initial and refreshed feedback.
   */
  private static function getFeedback(array $issues): TranslatableMarkup|string {
    return $issues ? new TranslatableMarkup('<strong>Not available:</strong> @reason', ['@reason' => implode(' ', array_map('strval', $issues))]) : '';
  }

}
