<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_blocks\Helpers\DescriptionLengthFeedback;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\Group;
use WebDriver\Key;

/**
 * Tests CTA alternatives and non-blocking feedback in the editing dialog.
 */
#[Group('ghi_blocks')]
class LinkCarouselCallToActionTest extends BlockUiBase {

  private const FORM = '#layout-builder-update-block';

  private const CTA_CONFIG = 'cta[items][item_config][plugin_config]';

  /**
   * Tests the active background covers every cell and follows the selection.
   */
  public function testActiveCallToActionRowHasDistinctBackground(): void {
    $this->createEditorFixture([
      'links' => ['items' => []],
      'cta' => [
        'items' => [
          $this->createCallToAction(1, 'First alternative', FALSE),
          $this->createCallToAction(2, 'Second alternative', FALSE),
        ],
        'active_item_id' => 1,
        'source_items' => [],
      ],
    ]);
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementTextContains('css', '.link-carousel-editor__caption strong', 'Choose one call-to-action (CTA) to display.');

    foreach ([1, 2] as $active_id) {
      if ($active_id === 2) {
        $this->clickOperation('call-to-action-activate-2');
      }
      $active = $this->getCallToActionCellBackgrounds($active_id);
      $inactive = $this->getCallToActionCellBackgrounds(3 - $active_id);
      $this->assertCount(1, array_unique($active));
      $this->assertNotSame($inactive[0], $active[0]);
      $this->assertNotSame('rgba(0, 0, 0, 0)', $active[0]);
    }
  }

  /**
   * Tests delayed feedback cannot mark a different record's fields.
   */
  public function testDelayedFeedbackDoesNotAffectAnotherEditor(): void {
    $this->createEditorFixture([
      'links' => ['items' => []],
      'cta' => [
        'items' => [
          $this->createCallToAction(1, 'First alternative', FALSE),
          $this->createCallToAction(2, 'Second alternative', FALSE),
        ],
        'active_item_id' => 1,
        'source_items' => [],
      ],
    ]);
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->clickOperation('edit-1');
    $build_selector = self::FORM . ' input[name="form_build_id"]';
    $first_build_id = $this->assertSession()->elementExists('css', $build_selector)->getValue();
    $form = self::FORM;
    // Hold a real response's commands while other editor actions complete.
    $this->getSession()->executeScript(<<<JS
const trigger = document.querySelector('$form [data-link-carousel-cta-refresh]');
const ajax = Drupal.ajax.instances.find(instance => instance && instance.element === trigger);
const success = ajax.success.bind(ajax);
ajax.success = (response, status) => {
  ajax.success = success;
  window.linkCarouselHeldFeedback = response;
  window.linkCarouselReleaseFeedback = () => success(response, status).then(() => {
    window.linkCarouselFeedbackReleased = true;
  });
  return Promise.resolve();
};
JS);
    $this->typeFocusedValue($this->getCtaField('label'), '');
    $this->getCtaField('description')->focus();
    $this->waitForAjaxToFinish();
    $this->assertTrue($this->getSession()->evaluateScript('JSON.stringify(window.linkCarouselHeldFeedback).includes("Add a title")'));
    $this->assertSame($first_build_id, $this->assertSession()->elementExists('css', $build_selector)->getValue());

    $this->clickOperation('item-config-cancel');
    $this->clickOperation('edit-2');
    $this->assertNotSame($first_build_id, $this->assertSession()->elementExists('css', $build_selector)->getValue());
    $this->assertSame('Second alternative', $this->getCtaField('label')->getValue());
    $highlight = self::FORM . ' [data-link-carousel-cta-field="label"].link-carousel-editor__field--unavailable';
    $this->assertSession()->elementNotExists('css', $highlight);

    $this->getSession()->executeScript('window.linkCarouselReleaseFeedback();');
    $this->assertTrue($this->getSession()->wait(5000, 'window.linkCarouselFeedbackReleased === true'));
    $this->assertSession()->elementNotExists('css', $highlight);
    $this->assertSession()->elementTextNotContains('css', self::FORM . ' [data-link-carousel-cta-field="label"]', 'Add a title');
    $this->assertSame('Second alternative', $this->getCtaField('label')->getValue());
  }

  /**
   * Tests dark-mode advice stays readable with matching borders and focus.
   */
  public function testDescriptionAdviceUsesDarkModeColours(): void {
    $this->config('gin.settings')->set('enable_darkmode', '1')->save();
    $cta = $this->createCallToAction(1, 'Support title', TRUE);
    $cta['config']['value']['description'] = '';
    $this->createEditorFixture([
      'links' => ['items' => []],
      'cta' => [
        'items' => [$cta],
        'active_item_id' => 1,
        'source_items' => [],
      ],
    ]);
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->clickOperation('edit-1');
    $this->assertSession()->elementExists('css', '.gin--dark-mode');
    $field = $this->getCtaField('description');
    $feedback = $this->assertSession()->elementExists('css', '#' . $field->getAttribute('data-description-length-feedback'));
    $guidance = $this->assertSession()->elementExists('css', self::FORM . ' .description-length-feedback__guidance');
    $wrapper = $this->assertSession()->elementExists('css', self::FORM . ' [data-link-carousel-cta-field="description"].link-carousel-editor__field--unavailable');
    $this->assertSame('rgb(59, 59, 63)', $this->getComputedCssProperty($wrapper, 'background-color'));
    $this->assertSame('rgb(59, 59, 63)', $this->getComputedCssProperty($field, 'background-color'));
    foreach ([
      DescriptionLengthFeedback::RECOMMENDED_LENGTH + 1 => 'rgb(255, 180, 91)',
      DescriptionLengthFeedback::LONG_LENGTH + 1 => 'rgb(255, 143, 133)',
    ] as $length => $colour) {
      $field->focus();
      $this->typeFocusedValue($field, str_repeat('a', $length));
      $this->assertBorderColor($field, $colour);
      $this->assertSame('2px', $this->getComputedCssProperty($field, 'border-top-width'));
      $this->assertSame($colour, $this->getComputedCssProperty($feedback, 'color'));
      $this->assertFocusShadow($field, $colour, '');
      $this->getCtaField('url')->focus();
      $this->waitForAjaxToFinish();
      $guidance->mouseOver();
      $this->assertBorderColor($field, $colour);
      $this->assertSame('2px', $this->getComputedCssProperty($field, 'border-top-width'));
    }
  }

  /**
   * Tests active selection, live availability and incomplete drafts.
   */
  public function testAlternativesPreserveDraftsAndRefreshFeedbackOnBlur(): void {
    $settings = [
      'links' => ['items' => []],
      'cta' => [
        'items' => [
          $this->createCallToAction(1, 'Original support title', TRUE),
          $this->createCallToAction(2, 'Original alternative title', FALSE),
        ],
        'active_item_id' => 1,
        'source_items' => [],
      ],
    ];
    [$node, $uuid, $tempstore_key] = $this->createEditorFixture($settings);
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $before_feedback = $this->getTemporarySettings($tempstore_key, $uuid);
    $this->assertCtaStatus(1, 'Active');
    $this->assertCtaStatus(2, 'Inactive');
    $this->assertSession()->elementNotExists('css', 'table[data-link-carousel-ctas] a.tabledrag-handle');

    $this->clickButtonWithText('Add new call to action');
    $this->waitForAjaxToFinish();
    $this->assertFalse($this->getCtaField('show_heart')->isChecked());
    $this->getCtaField('label')->setValue('An incomplete alternative');
    $this->waitForAjaxToFinish();
    $this->clickButtonWithText('Add call to action');
    $this->waitForAjaxToFinish();
    $this->assertCtaStatus(3, 'Not available');
    $this->clickOperation('call-to-action-activate-3');
    $this->assertCtaStatus(3, 'Active — not displayed');
    $this->assertCtaStatus(1, 'Inactive');
    $this->clickOperation('remove-3');
    $this->assertCtaStatus(1, 'Inactive');
    $this->assertCtaStatus(2, 'Inactive');

    $this->clickOperation('call-to-action-activate-2');
    $this->assertCtaStatus(1, 'Inactive');
    $this->assertCtaStatus(2, 'Active');
    $this->clickOperation('call-to-action-deactivate-2');
    $this->assertCtaStatus(1, 'Inactive');
    $this->assertCtaStatus(2, 'Inactive');
    $this->clickOperation('call-to-action-activate-1');
    $this->clickOperation('edit-1');

    $assert_session = $this->assertSession();
    $title = $this->getCtaField('label');
    $description = $this->getCtaField('description');
    $url = $this->getCtaField('url');
    $button_label = $this->getCtaField('button_label');
    $heart = $this->getCtaField('show_heart');
    $this->assertTrue($heart->isChecked());
    $heart->uncheck();
    $title_wrapper = self::FORM . ' [data-link-carousel-cta-field="label"]';
    $url_wrapper = self::FORM . ' [data-link-carousel-cta-field="url"]';
    $highlight = '.link-carousel-editor__field--unavailable';

    // Typing only updates length advice; availability refreshes after blur.
    $this->typeFocusedValue($title, '');
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $description->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('css', $title_wrapper . $highlight);
    $assert_session->elementTextContains('css', $title_wrapper, 'Not available: Add a title');
    $this->assertFocusedField($description->getAttribute('name'));

    $pending_description = 'Pending description kept while other fields refresh.';
    $this->typeFocusedValue($description, $pending_description);
    $title->focus();
    $this->waitForAjaxToFinish();
    $this->typeFocusedValue($title, 'Corrected support title');
    $description->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $this->assertFocusedField($description->getAttribute('name'));
    $this->assertSame($pending_description, $description->getValue());

    $this->typeFocusedValue($url, 'javascript:alert(1)');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('css', $url_wrapper . $highlight);
    $assert_session->elementTextContains('css', $url_wrapper, 'Not available: Enter a valid URL');
    $this->assertFocusedField($button_label->getAttribute('name'));
    $this->typeFocusedValue($url, 'https://example.org/updated-support');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $url_wrapper . $highlight);
    $assert_session->elementTextContains('css', $url_wrapper, 'Enter an HTTP or HTTPS URL');

    // A later change must survive an earlier request for the same field.
    $title_name = $title->getAttribute('name');
    $url_name = $url->getAttribute('name');
    $form = self::FORM;
    $this->getSession()->executeScript(<<<JS
const title = document.querySelector('$form input[name="$title_name"]');
const url = document.querySelector('$form input[name="$url_name"]');
const change = (field, value) => {
  field.value = value;
  field.dispatchEvent(new Event('change', {bubbles: true}));
};
change(title, '');
window.linkCarouselFeedbackQueued = document.querySelector('$form [data-link-carousel-cta-refresh]').disabled;
change(url, 'javascript:alert(2)');
change(title, 'Rapidly corrected title');
JS);
    $this->assertTrue($this->getSession()->evaluateScript('window.linkCarouselFeedbackQueued'));
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $assert_session->elementExists('css', $url_wrapper . $highlight);
    $this->assertSame('Rapidly corrected title', $title->getValue());
    $this->assertSame($pending_description, $description->getValue());
    $this->assertFocusedField($button_label->getAttribute('name'));
    $this->typeFocusedValue($url, 'https://example.org/updated-support');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $this->assertSame($settings, $this->getSavedSettings($node->id(), $uuid));
    $this->assertSame($before_feedback, $this->getTemporarySettings($tempstore_key, $uuid));

    // Saving an incomplete active record must retain it, without selecting a
    // different complete record or requiring the hidden feedback to finish.
    $this->typeFocusedValue($title, '');
    $description->focus();
    $this->waitForAjaxToFinish();
    $this->typeFocusedValue($button_label, 'Support pending campaign');
    $this->submitImmediatelyAfterBlur($button_label, 'input[name="item-config-submit"]');
    $this->waitForAjaxToFinish();
    $this->assertCtaStatus(1, 'Active — not displayed');
    $this->assertCtaStatus(2, 'Inactive');
    $this->assertSame($before_feedback, $this->getTemporarySettings($tempstore_key, $uuid));

    $this->clickOperation('edit-1');
    $this->assertSame('', $this->getCtaField('label')->getValue());
    $this->assertSame($pending_description, $this->getCtaField('description')->getValue());
    $this->assertSame('Support pending campaign', $this->getCtaField('button_label')->getValue());
    $this->assertFalse($this->getCtaField('show_heart')->isChecked());
    $this->clickElement('#layout-builder-modal input[name="links-button"]');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->assertSame('', $this->getCtaField('label')->getValue());
    $this->assertSame($pending_description, $this->getCtaField('description')->getValue());
    $this->clickButtonWithText('Update call to action');
    $this->waitForAjaxToFinish();
    $this->assertCtaStatus(1, 'Active — not displayed');
    $this->clickOperation('call-to-action-activate-2');
    $this->assertCtaStatus(2, 'Active');
    $this->clickOperation('edit-2');
    $this->assertFalse($this->getCtaField('show_heart')->isChecked());
    $this->typeFocusedValue($this->getCtaField('label'), '');
    $this->getCtaField('description')->focus();
    $this->waitForAjaxToFinish();
    $button_label = $this->getCtaField('button_label');
    $this->typeFocusedValue($button_label, 'Fresh final button label');
    $this->submitImmediatelyAfterBlur($button_label, 'input[data-drupal-selector="edit-actions-submit"]');
    $this->waitForAjaxToFinish();
    $this->assertTrue($assert_session->waitForElementRemoved('css', '#layout-builder-modal'));
    $pending = $this->getTemporarySettings($tempstore_key, $uuid);
    $this->assertNotNull($pending);
    $this->assertSame('2', (string) $pending['cta']['active_item_id']);
    $by_id = array_column($pending['cta']['items'], NULL, 'id');
    $this->assertCount(2, $by_id);
    $this->assertSame('', $by_id[1]['config']['label']);
    $this->assertSame($pending_description, $by_id[1]['config']['value']['description']);
    $this->assertSame('https://example.org/updated-support', $by_id[1]['config']['value']['url']);
    $this->assertSame('Support pending campaign', $by_id[1]['config']['value']['button_label']);
    $this->assertFalse($by_id[1]['config']['value']['show_heart']);
    $this->assertSame('', $by_id[2]['config']['label']);
    $this->assertSame('Fresh final button label', $by_id[2]['config']['value']['button_label']);
    $this->assertSame($settings, $this->getSavedSettings($node->id(), $uuid));
  }

  /**
   * Tests length feedback remains advisory for both news and CTA descriptions.
   */
  public function testDescriptionAdviceUpdatesWhileTypingAndAllowsSaving(): void {
    $settings = [
      'links' => [
        'items' => [
          [
            'id' => 1,
            'pid' => NULL,
            'item_type' => 'carousel_item',
            'weight' => 0,
            'config' => [
              'label' => 'News draft',
              'value' => ['description' => str_repeat('a', DescriptionLengthFeedback::RECOMMENDED_LENGTH)],
            ],
          ],
        ],
      ],
      'cta' => [
        'items' => [$this->createCallToAction(1, 'Support title', TRUE)],
        'active_item_id' => 1,
        'source_items' => [],
      ],
    ];
    [$node, $uuid, $tempstore_key] = $this->createEditorFixture($settings);
    $this->clickOperation('edit-1');
    $news_name = 'links[items][item_config][plugin_config][value][description]';
    $this->assertDescriptionThresholds($news_name);
    $this->clickButtonWithText('Update news item');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->clickOperation('edit-1');
    $this->assertDescriptionThresholds(self::CTA_CONFIG . '[value][description]');
    $submit = $this->assertSession()->elementExists('css', self::FORM . ' input[data-drupal-selector="edit-actions-submit"]');
    $this->assertFalse($submit->hasAttribute('disabled'));
    $this->clickElement(self::FORM . ' input[data-drupal-selector="edit-actions-submit"]');
    $this->waitForAjaxToFinish();
    $this->assertTrue($this->assertSession()->waitForElementRemoved('css', '#layout-builder-modal'));

    $pending = $this->getTemporarySettings($tempstore_key, $uuid);
    $news_by_id = array_column($pending['links']['items'], NULL, 'id');
    $cta_by_id = array_column($pending['cta']['items'], NULL, 'id');
    $this->assertSame(DescriptionLengthFeedback::LONG_LENGTH + 1, mb_strlen($news_by_id[1]['config']['value']['description']));
    $this->assertSame(DescriptionLengthFeedback::LONG_LENGTH + 1, mb_strlen($cta_by_id[1]['config']['value']['description']));
    $this->assertSame($settings, $this->getSavedSettings($node->id(), $uuid));
  }

  /**
   * Asserts live thresholds preserve textarea typography and keyboard focus.
   */
  private function assertDescriptionThresholds(string $name): void {
    $field = $this->assertSession()->fieldExists($name);
    $guidance = $this->assertSession()->elementExists('css', self::FORM . ' .description-length-feedback__guidance');
    $guidance->mouseOver();
    $normal_border = $this->getComputedCssProperty($field, 'border-top-color');
    $normal_width = $this->getComputedCssProperty($field, 'border-top-width');
    $field->mouseOver();
    $normal_hover_border = $this->getComputedCssProperty($field, 'border-top-color');
    $normal_hover_width = $this->getComputedCssProperty($field, 'border-top-width');
    $field->focus();
    $selector = 'textarea[name="' . $name . '"]';
    $script_selector = json_encode($selector);
    $feedback_selector = '#' . $field->getAttribute('data-description-length-feedback');
    $messages = $this->getSession()->evaluateScript('JSON.parse(document.querySelector(' . json_encode($feedback_selector) . ').dataset.descriptionLengthMessages)');
    $this->assertEqualsCanonicalizing(['recommended', 'long', 'very-long'], array_keys($messages));
    foreach ($messages as $message) {
      $this->assertIsString($message);
      $this->assertNotSame('', $message);
    }
    $status_selector = $feedback_selector . ' [data-description-length-status]';
    $this->assertSame($messages[$field->getAttribute('data-description-length-state')], $this->assertSession()->elementExists('css', $status_selector)->getText());
    $this->assertSession()->elementTextContains('css', $feedback_selector, mb_strlen($field->getValue()) . ' characters');
    $weight = $this->getComputedCssProperty($field, 'font-weight');
    $focus_shadow = $this->getComputedCssProperty($field, 'box-shadow');
    $normal_focus_border = $this->getComputedCssProperty($field, 'border-top-color');
    $normal_focus_width = $this->getComputedCssProperty($field, 'border-top-width');
    $this->assertNotSame('none', $focus_shadow);
    $cases = [
      [DescriptionLengthFeedback::RECOMMENDED_LENGTH, 'recommended', '500'],
      [DescriptionLengthFeedback::RECOMMENDED_LENGTH + 1, 'long', '600'],
      [DescriptionLengthFeedback::LONG_LENGTH, 'long', '600'],
      [DescriptionLengthFeedback::LONG_LENGTH + 1, 'very-long', '700'],
    ];
    foreach ($cases as [$length, $state, $feedback_weight]) {
      $this->typeFocusedValue($field, str_repeat('a', $length));
      $feedback_selector = '#' . $field->getAttribute('data-description-length-feedback');
      $this->assertSame($state, $field->getAttribute('data-description-length-state'));
      $this->assertSame($messages[$state], $this->assertSession()->elementExists('css', $status_selector)->getText());
      $this->assertSession()->elementTextContains('css', $feedback_selector, $length . ' characters');
      $counter = $this->assertSession()->elementExists('css', $feedback_selector . ' [data-description-length-count]');
      $this->assertSame($feedback_weight, $this->getComputedCssProperty($counter, 'font-weight'));
      $this->assertSame($weight, $this->getComputedCssProperty($field, 'font-weight'));
      $this->assertFocusedField($name);
      $this->assertFalse($field->hasAttribute('maxlength'));
      $this->assertFalse($field->hasAttribute('aria-invalid'));
      $this->assertTrue($this->getSession()->evaluateScript('document.querySelector(' . $script_selector . ').getAttribute("aria-describedby").split(/\s+/).every(id => document.getElementById(id) !== null)'));
      $advisory_border = match ($state) {
        'long' => 'rgb(230, 119, 0)',
        'very-long' => 'rgb(180, 35, 24)',
        default => NULL,
      };
      $this->assertBorderColor($field, $advisory_border ?? $normal_focus_border);
      $this->assertSame($advisory_border ? '2px' : $normal_focus_width, $this->getComputedCssProperty($field, 'border-top-width'));
      $this->assertFocusShadow($field, $advisory_border, $focus_shadow);
      $field->blur();
      if (str_starts_with($name, 'cta[')) {
        $this->waitForAjaxToFinish();
      }
      $guidance->mouseOver();
      $this->assertBorderColor($field, $advisory_border ?? $normal_border);
      $this->assertSame($advisory_border ? '2px' : $normal_width, $this->getComputedCssProperty($field, 'border-top-width'));
      $field->mouseOver();
      $this->assertBorderColor($field, $advisory_border ?? $normal_hover_border);
      $this->assertSame($advisory_border ? '2px' : $normal_hover_width, $this->getComputedCssProperty($field, 'border-top-width'));
      $field->focus();
      $this->assertFocusShadow($field, $advisory_border, $focus_shadow);
      // Native keyboard navigation must retain focus and its visible ring.
      $this->getSession()->getDriver()->getWebDriverSession()->element('xpath', $field->getXpath())->postValue(['text' => Key::HOME]);
      $this->assertFocusedField($name);
      $this->assertFocusShadow($field, $advisory_border, $focus_shadow);
    }
    $this->assertSession()->elementTextContains('css', $feedback_selector, 'Above the recommended maximum of ' . DescriptionLengthFeedback::LONG_LENGTH);

    // Paste emits the same native input event; it must not need a blur/AJAX
    // round trip before updating the count and visual advisory state.
    $paste_cases = [
      DescriptionLengthFeedback::RECOMMENDED_LENGTH => 'recommended',
      DescriptionLengthFeedback::LONG_LENGTH + 1 => 'very-long',
    ];
    foreach ($paste_cases as $length => $state) {
      $value = json_encode(str_repeat('b', $length));
      $this->getSession()->executeScript('const field = document.querySelector(' . $script_selector . '); field.value = ' . $value . '; field.dispatchEvent(new InputEvent("input", {bubbles: true, inputType: "insertFromPaste"}));');
      $this->assertSame($state, $field->getAttribute('data-description-length-state'));
      $this->assertSame($messages[$state], $this->assertSession()->elementExists('css', $status_selector)->getText());
      $this->assertSession()->elementTextContains('css', $feedback_selector, $length . ' characters');
      $this->assertFocusedField($name);
      $this->assertBorderColor($field, $state === 'recommended' ? $normal_focus_border : 'rgb(180, 35, 24)');
      $this->assertSame($state === 'recommended' ? $normal_focus_width : '2px', $this->getComputedCssProperty($field, 'border-top-width'));
      $this->assertFocusShadow($field, $state === 'recommended' ? NULL : 'rgb(180, 35, 24)', $focus_shadow);
    }
  }

  /**
   * Returns an element's actual browser-computed CSS property value.
   */
  private function getComputedCssProperty(NodeElement $element, string $property): string {
    $xpath = json_encode($element->getXpath());
    return $this->getSession()->evaluateScript('getComputedStyle(document.evaluate(' . $xpath . ', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue).getPropertyValue(' . json_encode($property) . ')');
  }

  /**
   * Asserts the final color after Gin's CSS transitions have completed.
   */
  private function assertBorderColor(NodeElement $field, string $color): void {
    $selector = json_encode('#' . $field->getAttribute('id'));
    $script = 'getComputedStyle(document.querySelector(' . $selector . ')).borderTopColor';
    $this->assertTrue($this->getSession()->wait(1000, $script . ' === ' . json_encode($color)));
    $this->assertSame($color, $this->getComputedCssProperty($field, 'border-top-color'));
  }

  /**
   * Asserts focused advisories use the border color without hiding the ring.
   */
  private function assertFocusShadow(NodeElement $field, ?string $color, string $normal_shadow): void {
    $selector = json_encode('#' . $field->getAttribute('id'));
    $script = 'getComputedStyle(document.querySelector(' . $selector . ')).boxShadow';
    $condition = $color !== NULL ? $script . '.includes(' . json_encode($color) . ')' : $script . ' === ' . json_encode($normal_shadow);
    $this->assertTrue($this->getSession()->wait(1000, $condition));
    $shadow = $this->getComputedCssProperty($field, 'box-shadow');
    $this->assertNotSame('none', $shadow);
    if ($color !== NULL) {
      $this->assertStringContainsString($color, $shadow);
    }
    else {
      $this->assertSame($normal_shadow, $shadow);
    }
  }

  /**
   * Creates an actual Layout Builder component and opens its editing dialog.
   */
  private function createEditorFixture(array $settings): array {
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'generic_link_carousel',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => $settings,
    ]);
    $node = $this->createNode();
    // Existing components remain editable where adding new ones is restricted.
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($node)]);
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    return [$node, $uuid, $storage->getTempstoreKey()];
  }

  /**
   * Creates a complete CTA alternative without rendering dependencies.
   */
  private function createCallToAction(int $id, string $title, bool $heart): array {
    return [
      'id' => $id,
      'pid' => NULL,
      'item_type' => 'call_to_action',
      'weight' => $id - 1,
      'config' => [
        'label' => $title,
        'value' => [
          'description' => 'Original support description',
          'url' => 'https://example.org/support-' . $id,
          'button_label' => 'Support now',
          'show_heart' => $heart,
        ],
      ],
    ];
  }

  /**
   * Returns a field from the CTA record currently being edited.
   */
  private function getCtaField(string $key): NodeElement {
    $name = self::CTA_CONFIG . ($key === 'label' ? '[label]' : '[value][' . $key . ']');
    return $this->assertSession()->fieldExists($name);
  }

  /**
   * Clicks a built configuration-container or CTA-selection button.
   */
  private function clickOperation(string $name): void {
    $this->clickElement('#layout-builder-modal input[name="' . $name . '"]');
    $this->waitForAjaxToFinish();
  }

  /**
   * Asserts the status of a particular saved CTA alternative.
   */
  private function assertCtaStatus(int $id, string $status): void {
    $selector = 'table[data-link-carousel-ctas] input[name="edit-' . $id . '"]';
    $this->assertSession()->elementExists('css', $selector);
    $selector = json_encode($selector);
    $text = $this->getSession()->evaluateScript('Array.from(document.querySelector(' . $selector . ').closest("tr").querySelector(".link-carousel-editor__cta-status").childNodes).filter(node => node.nodeType === Node.TEXT_NODE).map(node => node.textContent).join("").trim()');
    $this->assertSame($status, $text);
  }

  /**
   * Returns the actual backgrounds, including the actions cell, for a CTA row.
   */
  private function getCallToActionCellBackgrounds(int $id): array {
    $selector = json_encode('table[data-link-carousel-ctas] input[name="edit-' . $id . '"]');
    return $this->getSession()->evaluateScript('Array.from(document.querySelector(' . $selector . ').closest("tr").cells, cell => getComputedStyle(cell).backgroundColor)');
  }

  /**
   * Types through WebDriver without Mink's automatic blur after setValue().
   */
  private function typeFocusedValue(NodeElement $field, string $value): void {
    $element = $this->getSession()->getDriver()->getWebDriverSession()->element('xpath', $field->getXpath());
    $element->postValue(['text' => Key::CONTROL . 'a' . Key::NULL_KEY . Key::BACKSPACE . $value]);
  }

  /**
   * Starts a submission while field feedback still has an outstanding request.
   */
  private function submitImmediatelyAfterBlur(NodeElement $field, string $submit_selector): void {
    $field_selector = json_encode('[name="' . $field->getAttribute('name') . '"]');
    $submit = $this->assertSession()->elementExists('css', self::FORM . ' ' . $submit_selector);
    $this->assertFalse($submit->hasAttribute('disabled'));
    $submit_selector = json_encode(self::FORM . ' ' . $submit_selector);
    $form = self::FORM;
    // Dispatch the click in the same task as blur so feedback is still pending.
    // Check the control is enabled first: synthetic clicks bypass that guard.
    $this->getSession()->executeScript(<<<JS
const field = document.querySelector($field_selector);
const submit = document.querySelector($submit_selector);
field.blur();
window.linkCarouselFeedbackInputDisabled = field.disabled;
window.linkCarouselFeedbackTriggerDisabled = document.querySelector('$form [data-link-carousel-cta-refresh]').disabled;
['mousedown', 'mouseup', 'click'].forEach((type) => {
  submit.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, view: window}));
});
JS);
    $this->assertFalse($this->getSession()->evaluateScript('window.linkCarouselFeedbackInputDisabled'));
    $this->assertTrue($this->getSession()->evaluateScript('window.linkCarouselFeedbackTriggerDisabled'));
  }

  /**
   * Asserts feedback did not take focus away from the next input.
   */
  private function assertFocusedField(string $name): void {
    $this->assertSame($name, $this->getSession()->evaluateScript('document.activeElement.name'));
  }

  /**
   * Returns the persisted settings, bypassing entity static caches.
   */
  private function getSavedSettings(int|string $nid, string $uuid): array {
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($nid);
    return $node->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponent($uuid)->toArray()['configuration']['hpc'];
  }

  /**
   * Returns temporary settings without populating repository caches.
   */
  private function getTemporarySettings(string $key, string $uuid): ?array {
    $entry = $this->container->get('tempstore.shared')->get('layout_builder.section_storage.overrides')->get($key);
    return $entry ? $entry['section_storage']->getSection(0)->getComponent($uuid)->toArray()['configuration']['hpc'] : NULL;
  }

}
