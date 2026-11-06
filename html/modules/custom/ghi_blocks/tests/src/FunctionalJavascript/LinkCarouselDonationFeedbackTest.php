<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\Group;
use WebDriver\Key;

/**
 * Tests nonblocking donation feedback in the actual block editing dialog.
 */
#[Group('ghi_blocks')]
class LinkCarouselDonationFeedbackTest extends BlockUiBase {

  /**
   * Tests native change events preserve focus, pending values, and draft saves.
   */
  public function testDonationFeedbackOnBlur(): void {
    $donation = [
      'enabled' => TRUE,
      'title' => 'Original donation title',
      'description' => 'Original donation description',
      'url' => 'https://example.org/donate',
      'button_label' => 'Donate now',
      'source_items' => [],
    ];
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'generic_link_carousel',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => ['links' => ['items' => []], 'donation' => $donation],
    ]);
    $node = $this->createNode();
    // Existing components remain editable even where new placements are denied.
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($node)]);
    $tempstore_key = $storage->getTempstoreKey();
    $this->loginEditor(['configure any layout']);

    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-modal input[name="donation-button"]');
    $this->waitForAjaxToFinish();

    $assert_session = $this->assertSession();
    $form = '#layout-builder-update-block';
    $title = $assert_session->elementExists('css', $form . ' input[name="donation[title]"]');
    $description = $assert_session->elementExists('css', $form . ' textarea[name="donation[description]"]');
    $url = $assert_session->elementExists('css', $form . ' input[name="donation[url]"]');
    $button_label = $assert_session->elementExists('css', $form . ' input[name="donation[button_label]"]');
    $title_wrapper = $form . ' [data-link-carousel-donation-field="title"]';
    $url_wrapper = $form . ' [data-link-carousel-donation-field="url"]';
    $highlight = '.link-carousel-editor__field--unavailable';
    $before_feedback = $this->getTemporaryDonation($tempstore_key, $uuid);

    // Native typing does not dispatch change until focus leaves the input.
    $this->typeFocusedValue($title, '');
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $description->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('css', $title_wrapper . $highlight);
    $assert_session->elementTextContains('css', $title_wrapper, 'Not available: Add a title');
    $this->assertFocusedField('donation[description]');

    $pending_description = 'Pending description kept while other fields refresh.';
    $this->typeFocusedValue($description, $pending_description);
    $title->focus();
    $this->waitForAjaxToFinish();
    $this->typeFocusedValue($title, 'Corrected donation title');
    $description->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $assert_session->elementTextNotContains('css', $title_wrapper, 'Not available:');
    $this->assertFocusedField('donation[description]');
    $this->assertSame($pending_description, $description->getValue());

    $this->typeFocusedValue($url, 'javascript:alert(1)');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('css', $url_wrapper . $highlight);
    $assert_session->elementTextContains('css', $url_wrapper, 'Not available: Enter a valid URL');
    $this->assertFocusedField('donation[button_label]');
    $this->typeFocusedValue($url, 'https://example.org/updated-donation');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $url_wrapper . $highlight);
    $assert_session->elementTextContains('css', $url_wrapper, 'Enter an HTTP or HTTPS URL');
    $this->assertFocusedField('donation[button_label]');
    $this->assertSame($pending_description, $description->getValue());

    // Several changes in one event loop must be queued behind the hidden
    // trigger's request, including a newer value for an already queued field.
    $this->getSession()->executeScript(<<<JS
const title = document.querySelector('$form input[name="donation[title]"]');
const url = document.querySelector('$form input[name="donation[url]"]');
const change = (field, value) => {
  field.value = value;
  field.dispatchEvent(new Event('change', {bubbles: true}));
};
change(title, '');
window.linkCarouselFeedbackQueued = document.querySelector('$form [data-link-carousel-donation-refresh]').disabled;
change(url, 'javascript:alert(2)');
change(title, 'Rapidly corrected title');
JS);
    $this->assertTrue($this->getSession()->evaluateScript('window.linkCarouselFeedbackQueued'));
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', $title_wrapper . $highlight);
    $assert_session->elementExists('css', $url_wrapper . $highlight);
    $this->assertSame('Rapidly corrected title', $title->getValue());
    $this->assertSame($pending_description, $description->getValue());
    $this->assertFocusedField('donation[button_label]');
    $this->typeFocusedValue($url, 'https://example.org/updated-donation');
    $button_label->focus();
    $this->waitForAjaxToFinish();
    $this->assertSame($donation, $this->getSavedDonation($node->id(), $uuid));
    $this->assertSame($before_feedback, $this->getTemporaryDonation($tempstore_key, $uuid));

    // Submit immediately after blur. Only the hidden trigger may be disabled;
    // the final Update must still serialize the visible field's fresh value.
    $this->typeFocusedValue($title, '');
    $description->focus();
    $this->waitForAjaxToFinish();
    $this->typeFocusedValue($button_label, 'Support pending campaign');
    $this->assertSame('Support pending campaign', $button_label->getValue());
    $this->getSession()->executeScript(<<<JS
const field = document.querySelector('$form input[name="donation[button_label]"]');
const submit = document.querySelector('$form input[data-drupal-selector="edit-actions-submit"]');
field.blur();
window.linkCarouselFeedbackInputDisabled = field.disabled;
window.linkCarouselFeedbackTriggerDisabled = document.querySelector('$form [data-link-carousel-donation-refresh]').disabled;
['mousedown', 'mouseup', 'click'].forEach((type) => {
  submit.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, view: window}));
});
JS);
    $this->assertFalse($this->getSession()->evaluateScript('window.linkCarouselFeedbackInputDisabled'));
    $this->assertTrue($this->getSession()->evaluateScript('window.linkCarouselFeedbackTriggerDisabled'));
    $this->waitForAjaxToFinish();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->elementNotExists('css', '#layout-builder-modal');
    $pending = $this->getTemporaryDonation($tempstore_key, $uuid);
    $this->assertNotNull($pending);
    $this->assertSame('', $pending['title']);
    $this->assertSame($pending_description, $pending['description']);
    $this->assertSame('https://example.org/updated-donation', $pending['url']);
    $this->assertSame('Support pending campaign', $pending['button_label']);
    $this->assertSame($donation, $this->getSavedDonation($node->id(), $uuid));
  }

  /**
   * Types through WebDriver without Mink's automatic blur after setValue().
   */
  private function typeFocusedValue(NodeElement $field, string $value): void {
    $element = $this->getSession()->getDriver()->getWebDriverSession()->element('xpath', $field->getXpath());
    $element->postValue(['text' => Key::CONTROL . 'a' . Key::NULL_KEY . Key::BACKSPACE . $value]);
  }

  /**
   * Asserts feedback did not take focus away from the next input.
   */
  private function assertFocusedField(string $name): void {
    $this->assertSame($name, $this->getSession()->evaluateScript('document.activeElement.name'));
  }

  /**
   * Returns the persisted component settings, bypassing entity static caches.
   */
  private function getSavedDonation(int|string $nid, string $uuid): array {
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($nid);
    return $node->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponent($uuid)->toArray()['configuration']['hpc']['donation'];
  }

  /**
   * Returns temporary component settings without populating repository caches.
   */
  private function getTemporaryDonation(string $key, string $uuid): ?array {
    $entry = $this->container->get('tempstore.shared')->get('layout_builder.section_storage.overrides')->get($key);
    return $entry ? $entry['section_storage']->getSection(0)->getComponent($uuid)->toArray()['configuration']['hpc']['donation'] : NULL;
  }

}
