<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\ghi_form_elements\Element\ConfigurationContainer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests whether child editors allow the enclosing form to be submitted.
 */
#[CoversClass(ConfigurationContainer::class)]
#[Group('ghi_form_elements')]
class ConfigurationContainerOuterSubmitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderRoot')->willReturn('');
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests existing containers must explicitly allow enclosing form submission.
   */
  public function testOuterSubmitIsDisabledByDefault(): void {
    $element = new ConfigurationContainer([], 'configuration_container', []);
    $this->assertFalse($element->getInfo()['#allow_outer_submit']);
  }

  /**
   * Tests AJAX keeps submit and preview available only when opted in.
   *
   * @param string|null $editor
   *   The child editor key, or NULL for the item list.
   * @param bool|null $allow_outer_submit
   *   The opt-in value, or NULL when the property is not set.
   * @param bool $disabled
   *   Whether the enclosing controls should be disabled.
   */
  #[DataProvider('outerSubmitProvider')]
  public function testOuterSubmitAvailability(?string $editor, ?bool $allow_outer_submit, bool $disabled): void {
    $element = [
      '#array_parents' => ['settings', 'container', 'items'],
      '#attached' => [],
    ];
    if ($allow_outer_submit !== NULL) {
      $element['#allow_outer_submit'] = $allow_outer_submit;
    }
    if ($editor !== NULL) {
      $element[$editor] = [];
    }
    $form_state = new FormState();
    ConfigurationContainer::prepareAjaxForm($element, $form_state);
    $form = ['settings' => ['container' => ['items' => $element]]];
    $trigger = ['#ajax' => ['wrapper' => 'items-wrapper']];
    $form_state->setTriggeringElement($trigger);

    $commands = ConfigurationContainer::updateAjax($form, $form_state)->getCommands();

    $this->assertCount(3, $commands);
    $this->assertSame('#items-wrapper', $commands[0]['selector']);
    foreach ([
      1 => '[data-drupal-selector="edit-actions-submit"]',
      2 => '[data-drupal-selector="edit-actions-subforms-preview"]',
    ] as $index => $selector) {
      $this->assertSame([
        'command' => 'invoke',
        'selector' => $selector,
        'method' => $disabled ? 'attr' : 'removeAttr',
        'args' => $disabled ? ['disabled', 'disabled'] : ['disabled'],
      ], $commands[$index]);
    }
  }

  /**
   * Provides list and child-editor states with and without explicit opt-in.
   */
  public static function outerSubmitProvider(): array {
    $cases = [];
    $editors = [
      'list' => NULL,
      'item' => 'item_config',
      'filter' => 'filter_config',
      'custom' => 'custom_config',
    ];
    foreach ($editors as $mode => $editor) {
      foreach (['default' => NULL, 'disabled' => FALSE, 'enabled' => TRUE] as $setting => $allow_outer_submit) {
        $cases[$mode . ', ' . $setting] = [
          $editor,
          $allow_outer_submit,
          $editor !== NULL && $allow_outer_submit !== TRUE,
        ];
      }
    }
    return $cases;
  }

}
