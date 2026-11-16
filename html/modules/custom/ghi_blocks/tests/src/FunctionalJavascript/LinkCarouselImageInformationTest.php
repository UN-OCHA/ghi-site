<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use WebDriver\Key;

/**
 * Tests the public main-news image information overlay.
 */
#[Group('ghi_blocks')]
class LinkCarouselImageInformationTest extends BlockUiBase {

  /**
   * Tests caption layering, keyboard access and pointer focus.
   */
  public function testImageInformationOverlay(): void {
    ImageStyle::create(['name' => 'link_carousel_feature_thumbnail', 'label' => 'Feature thumbnail'])->save();
    ResponsiveImageStyle::create([
      'id' => 'link_carousel_feature',
      'label' => 'Feature image',
      'breakpoint_group' => 'common_design',
      'fallback_image_style' => 'link_carousel_feature_thumbnail',
    ])->save();
    $uri = $this->container->get('file_system')->copy(DRUPAL_ROOT . '/core/tests/fixtures/files/image-test.png', 'public://image-information.png');
    $file = File::create(['uri' => $uri, 'status' => TRUE]);
    $file->save();
    $items = [];
    foreach (['Main story', 'Featured story'] as $id => $title) {
      $items[] = [
        'id' => $id,
        'pid' => NULL,
        'item_type' => 'carousel_item',
        'weight' => $id,
        'config' => [
          'label' => $title,
          'value' => [
            'image' => [$file->id()],
            'image_caption' => str_repeat('A caption for ' . $title . '. ', 20),
            'image_credit' => str_repeat('A credit for ' . $title . '. ', 20),
            'description' => str_repeat('A longer description remains beside the image. ', 20),
            'url' => 'https://example.org/story-' . $id,
          ],
        ],
      ];
    }
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', [
      'id' => 'generic_link_carousel',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => [
        'links' => ['items' => $items],
        'cta' => ['items' => [], 'active_item_id' => NULL],
      ],
    ]);
    $node = $this->createNode(['status' => TRUE]);
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();

    $this->getSession()->resizeWindow(1440, 1000);
    $this->drupalGet($node->toUrl());
    $toggle = $this->assertSession()->waitForElement('css', '.link-carousel-feature__caption-toggle');
    $this->assertNotNull($toggle, sprintf('Image information is missing at %s. Page text: %s', $this->getSession()->getCurrentUrl(), mb_substr($this->getSession()->getPage()->getText(), 0, 1000)));
    $this->assertSession()->elementsCount('css', '.link-carousel-feature__caption-toggle', 1);
    $caption = $this->assertSession()->elementExists('css', '.link-carousel-feature__caption-content');
    $title = $this->assertSession()->elementExists('css', '.link-carousel-feature__title');
    $action = $this->assertSession()->elementExists('css', '.link-carousel-feature__action a');
    $this->assertSame('Image information', $toggle->getAttribute('aria-label'));
    $this->assertSame($caption->getAttribute('id'), $toggle->getAttribute('aria-describedby'));
    $session = $this->getSession()->getDriver()->getWebDriverSession();
    $toggle_element = $session->element('xpath', $toggle->getXpath());
    $caption_element = $session->element('xpath', $caption->getXpath());
    // Establish mouse focus without navigating away from the fixture.
    $this->getSession()->executeScript("document.querySelector('.link-carousel-feature__action a').addEventListener('click', (event) => event.preventDefault());");

    foreach ([1440, 393] as $width) {
      $this->getSession()->resizeWindow($width, 1000);
      $title->mouseOver();
      $this->assertCaptionVisibility(FALSE);

      $toggle_element->postValue(['text' => Key::SPACE]);
      $this->assertCaptionVisibility(TRUE);
      // The information icon should disappear underneath the open caption.
      $this->assertTrue($this->getSession()->evaluateScript(<<<JS
(() => {
  const toggle = document.querySelector('.link-carousel-feature__caption-toggle');
  const rect = toggle.getBoundingClientRect();
  const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
  return document.querySelector('.link-carousel-feature__caption-content').contains(hit);
})()
JS));
      $text = $caption_element->property('textContent');
      $this->assertStringContainsString('A caption for Main story', $text);
      $this->assertStringContainsString('A credit for Main story', $text);
      // A tall introduction must not stretch the overlay below the image.
      $this->assertTrue($this->getSession()->evaluateScript(<<<JS
(() => {
  const image = document.querySelector('.link-carousel-feature__image img').getBoundingClientRect();
  return ['.link-carousel-feature__caption-toggle', '.link-carousel-feature__caption-content'].every((selector) => {
    const overlay = document.querySelector(selector).getBoundingClientRect();
    return overlay.left >= image.left && overlay.right <= image.right && overlay.top >= image.top && overlay.bottom <= image.bottom;
  });
})()
JS));

      $toggle_element->postValue(['text' => Key::TAB]);
      $this->assertTrue($this->getSession()->evaluateScript("document.activeElement.matches('.link-carousel-feature__caption-content')"));
      $this->assertCaptionVisibility(TRUE);
      $this->assertSame('rgb(255, 255, 255)', $this->getSession()->evaluateScript('getComputedStyle(document.activeElement).backgroundColor'));
      $this->assertSame('2px', $this->getSession()->evaluateScript('getComputedStyle(document.activeElement).outlineWidth'));
      if ($width === 393) {
        $this->assertTrue($this->getSession()->evaluateScript("document.activeElement.scrollHeight > document.activeElement.clientHeight"));
        $caption_element->postValue(['text' => Key::PAGE_DOWN]);
        $this->assertTrue($this->getSession()->wait(1000, "document.querySelector('.link-carousel-feature__caption-content').scrollTop > 0"));
      }
      // Isolate keyboard focus from any hover left over after resizing.
      $title->mouseOver();
      $this->assertCaptionVisibility(TRUE);
      $caption_element->postValue(['text' => Key::TAB]);
      $this->assertCaptionVisibility(FALSE);

      // Pointer focus must not keep the caption open after leaving the image.
      $action->click();
      $this->assertTrue($this->getSession()->evaluateScript("document.activeElement.matches('.link-carousel-feature__action a')"));
      $this->assertFalse($this->getSession()->evaluateScript("document.activeElement.matches(':focus-visible')"));
      $this->getSession()->executeScript("document.querySelector('.link-carousel-feature__caption-toggle').focus({preventScroll: true});");
      $this->assertTrue($this->getSession()->evaluateScript("document.activeElement.matches('.link-carousel-feature__caption-toggle')"));
      $this->assertFalse($this->getSession()->evaluateScript("document.activeElement.matches(':focus-visible')"));
      $this->assertCaptionVisibility(FALSE);

      $toggle_element->postValue(['text' => Key::SPACE]);
      $this->assertCaptionVisibility(TRUE);
      $caption->click();
      $this->assertTrue($this->getSession()->evaluateScript("document.activeElement.matches('.link-carousel-feature__caption-content')"));
      $this->assertFalse($this->getSession()->evaluateScript("document.activeElement.matches(':focus-visible')"));
      $this->assertCaptionVisibility(TRUE);
      $title->mouseOver();
      $this->assertCaptionVisibility(FALSE);
    }
  }

  /**
   * Checks caption state without Chrome 103's incorrect visibility result.
   */
  private function assertCaptionVisibility(bool $visible): void {
    $caption = $this->getSession()->getDriver()->getWebDriverSession()->element('css selector', '.link-carousel-feature__caption-content');
    $this->assertSame($visible ? 'block' : 'none', $caption->css('display'));
    if ($visible) {
      $this->assertSame('visible', $caption->css('visibility'));
      $rect = $caption->rect();
      $this->assertGreaterThan(0, $rect['width']);
      $this->assertGreaterThan(0, $rect['height']);
    }
  }

}
