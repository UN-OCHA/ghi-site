<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_blocks\Helpers\DescriptionLengthFeedback;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests advisory description length feedback without enforcing input limits.
 */
#[Group('ghi_blocks')]
class DescriptionLengthFeedbackTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests server counts, threshold boundaries and client configuration.
   */
  #[DataProvider('descriptionProvider')]
  public function testFeedbackIsAdvisoryAndUsesSharedThresholds(string $value, int $count, string $state): void {
    $element = DescriptionLengthFeedback::apply([
      '#type' => 'textarea',
      '#id' => 'edit-description',
      '#value' => $value,
      '#description' => $this->t('Existing field guidance.'),
    ]);

    $element = DescriptionLengthFeedback::afterBuild($element, new FormState());

    $attributes = $element['#attributes'];
    $component = $element['#description'];
    $feedback = $component['#props'];
    $this->assertSame('component', $component['#type']);
    $this->assertSame('ghi_blocks:description-length-feedback', $component['#component']);
    $this->assertSame($state, $attributes['data-description-length-state']);
    $this->assertSame($state, $feedback['state']);
    $this->assertSame(DescriptionLengthFeedback::RECOMMENDED_LENGTH, $attributes['data-description-length-recommended']);
    $this->assertSame(DescriptionLengthFeedback::LONG_LENGTH, $attributes['data-description-length-long']);
    $this->assertSame('edit-description--length-feedback', $attributes['data-description-length-feedback']);
    $this->assertSame($attributes['data-description-length-feedback'], $feedback['feedback_id']);
    $this->assertSame($count . ($count === 1 ? ' character' : ' characters'), $feedback['count_label']);
    $this->assertSame(['recommended', 'long', 'very-long'], array_keys($feedback['messages']));
    $this->assertSame('Within the recommended length.', $feedback['messages']['recommended']);
    $this->assertSame('Long description. Consider shortening it.', $feedback['messages']['long']);
    $this->assertSame('Above the recommended maximum of ' . DescriptionLengthFeedback::LONG_LENGTH . ' characters. Consider shortening it.', $feedback['messages']['very-long']);
    $this->assertArrayNotHasKey('status', $feedback);
    $this->assertSame('Existing field guidance.', (string) $component['#slots']['original_help']['#markup']);
    $this->assertStringContainsString((string) DescriptionLengthFeedback::RECOMMENDED_LENGTH, $feedback['guidance']);
    $this->assertStringContainsString('recommended maximum of ' . DescriptionLengthFeedback::LONG_LENGTH, $feedback['guidance']);
    $this->assertStringContainsString('You can still save.', $feedback['guidance']);
    $this->assertSame($value, $element['#value']);
    $this->assertArrayNotHasKey('#maxlength', $element);
    $this->assertArrayNotHasKey('#element_validate', $element);
    $this->assertArrayNotHasKey('aria-invalid', $attributes);
  }

  /**
   * Provides raw input, including Unicode, line endings and literal markup.
   */
  public static function descriptionProvider(): array {
    return [
      'empty' => ['', 0, 'recommended'],
      'one character' => ['a', 1, 'recommended'],
      'recommended boundary' => [
        str_repeat('a', DescriptionLengthFeedback::RECOMMENDED_LENGTH),
        DescriptionLengthFeedback::RECOMMENDED_LENGTH,
        'recommended',
      ],
      'above recommendation' => [
        str_repeat('a', DescriptionLengthFeedback::RECOMMENDED_LENGTH + 1),
        DescriptionLengthFeedback::RECOMMENDED_LENGTH + 1,
        'long',
      ],
      'long boundary' => [
        str_repeat('a', DescriptionLengthFeedback::LONG_LENGTH),
        DescriptionLengthFeedback::LONG_LENGTH,
        'long',
      ],
      'above long boundary' => [
        str_repeat('a', DescriptionLengthFeedback::LONG_LENGTH + 1),
        DescriptionLengthFeedback::LONG_LENGTH + 1,
        'very-long',
      ],
      'Unicode and literal markup' => ["😀\r\n<b>x</b>\r", 11, 'recommended'],
      'spaces are not removed' => ['  text  ', 8, 'recommended'],
    ];
  }

  /**
   * Tests current input takes priority over the initially configured value.
   */
  public function testFeedbackUsesProcessedInputOnRebuild(): void {
    $element = DescriptionLengthFeedback::apply([
      '#type' => 'textarea',
      '#id' => 'edit-description',
      '#default_value' => str_repeat('a', DescriptionLengthFeedback::LONG_LENGTH + 1),
      '#value' => 'Short current input.',
    ]);

    $element = DescriptionLengthFeedback::afterBuild($element, new FormState());

    $this->assertSame('recommended', $element['#attributes']['data-description-length-state']);
    $this->assertSame('20 characters', $element['#description']['#props']['count_label']);
  }

  /**
   * Tests external and core description associations are preserved together.
   */
  public function testDescriptionsAndExistingValidationArePreserved(): void {
    $description = ['#markup' => '<em>Original help.</em>'];
    $element = DescriptionLengthFeedback::apply([
      '#type' => 'textarea',
      '#id' => 'edit-description',
      '#description' => $description,
      '#attributes' => [
        'aria-describedby' => ['additional-guidance', 'shared-guidance'],
        'aria-invalid' => 'true',
      ],
      '#element_validate' => ['existing_validator'],
      '#after_build' => ['existing_after_build'],
    ]);
    // Simulate FormBuilder's automatic association with the field description.
    $element['#attributes']['aria-describedby'] = 'edit-description--description shared-guidance';

    $element = DescriptionLengthFeedback::afterBuild($element, new FormState());

    $this->assertSame('additional-guidance shared-guidance edit-description--description', $element['#attributes']['aria-describedby']);
    $this->assertSame($description, $element['#description']['#slots']['original_help']);
    $this->assertSame('true', $element['#attributes']['aria-invalid']);
    $this->assertSame(['existing_validator'], $element['#element_validate']);
    $this->assertSame('existing_after_build', $element['#after_build'][0]);
  }

  /**
   * Tests repeated enabling does not duplicate callbacks or guidance.
   */
  public function testEnablingFeedbackIsIdempotent(): void {
    $element = ['#type' => 'textarea', '#description' => $this->t('Existing guidance.')];

    $element = DescriptionLengthFeedback::apply(DescriptionLengthFeedback::apply($element));

    $this->assertSame([[DescriptionLengthFeedback::class, 'afterBuild']], $element['#after_build']);
    $this->assertArrayNotHasKey('#attached', $element);
    $this->assertSame('Existing guidance.', (string) $element['#description']);
  }

}
