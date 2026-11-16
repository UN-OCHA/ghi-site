<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Helpers\DescriptionLengthFeedback;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests module-owned feedback markup and automatic component assets.
 */
#[Group('ghi_blocks')]
class DescriptionLengthFeedbackComponentTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field'];

  /**
   * Tests component discovery, escaping, accessible feedback and asset loading.
   */
  public function testModuleComponentRendersWithItsAssets(): void {
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $element = DescriptionLengthFeedback::apply([
      '#type' => 'textarea',
      '#id' => 'edit-description',
      '#value' => str_repeat('a', DescriptionLengthFeedback::LONG_LENGTH + 1),
      '#description' => ['#plain_text' => 'Original <script> help.'],
    ]);
    $element = DescriptionLengthFeedback::afterBuild($element, new FormState());
    $build = $element['#description'];
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString('Original &lt;script&gt; help.', $html);
    $this->assertStringContainsString('id="edit-description--length-feedback"', $html);
    $this->assertStringContainsString('data-description-length-state="very-long"', $html);
    $this->assertStringContainsString('351 characters', $html);
    $this->assertStringContainsString('aria-live="polite"', $html);
    $this->assertStringContainsString('aria-atomic="true"', $html);
    $this->assertStringContainsString('recommended maximum of 350', $html);
    $xpath = new \DOMXPath(Html::load($html));
    $feedback = $xpath->query('//*[@data-description-length-messages]')->item(0);
    $messages = json_decode($feedback->getAttribute('data-description-length-messages'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame($element['#description']['#props']['messages'], $messages);
    $this->assertSame($messages['very-long'], $xpath->query('//*[@data-description-length-status]')->item(0)->textContent);
    $this->assertContains('core/components.ghi_blocks--description-length-feedback', $build['#attached']['library']);
    $library = $this->container->get('library.discovery')->getLibraryByName('core', 'components.ghi_blocks--description-length-feedback');
    $this->assertContains('core/drupal', $library['dependencies']);
    $this->assertContains('core/once', $library['dependencies']);
    $this->assertStringEndsWith('components/description-length-feedback/description-length-feedback.js', $library['js'][0]['data']);
    $this->assertStringEndsWith('components/description-length-feedback/description-length-feedback.css', $library['css'][0]['data']);
  }

  /**
   * Tests each instance carries its own safely escaped translated messages.
   */
  public function testInstanceMessagesAreEscapedAndKeptSeparate(): void {
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $build = [];
    $expected = [];
    foreach (['first', 'second'] as $id) {
      $element = DescriptionLengthFeedback::apply([
        '#type' => 'textarea',
        '#id' => $id,
        '#value' => str_repeat('a', DescriptionLengthFeedback::RECOMMENDED_LENGTH + 1),
      ]);
      $element = DescriptionLengthFeedback::afterBuild($element, new FormState());
      $component = $element['#description'];
      $component['#props']['messages']['long'] = $id . ': "quoted" <script>alert(1)</script> & plain text';
      $build[$id] = $component;
      $expected[$id] = $component['#props']['messages'];
    }
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $xpath = new \DOMXPath(Html::load($html));

    foreach ($expected as $id => $messages) {
      $feedback = $xpath->query('//*[@id="' . $id . '--length-feedback"]')->item(0);
      $this->assertSame($messages, json_decode($feedback->getAttribute('data-description-length-messages'), TRUE, 512, JSON_THROW_ON_ERROR));
      $this->assertSame($messages['long'], $xpath->query('.//*[@data-description-length-status]', $feedback)->item(0)->textContent);
    }
    $this->assertStringNotContainsString('<script>', $html);
  }

}
