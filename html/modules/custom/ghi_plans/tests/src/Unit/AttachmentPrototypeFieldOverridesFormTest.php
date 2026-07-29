<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Form\AttachmentPrototypeFieldOverridesForm;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the attachment prototype field overrides form.
 */
class AttachmentPrototypeFieldOverridesFormTest extends UnitTestCase {

  /**
   * Tests that additions and replacements use separate input fields.
   */
  public function testBuildFormSeparatesStoredOverrides(): void {
    $stored_overrides = [
      'additions' => [
        '1234' => [
          'metric_type' => 'cumulative_reach',
          'field_group' => 'planning',
        ],
      ],
      'replacements' => [
        '5678' => [
          '6' => [
            'metric_type' => 'cumulative_reach',
            'field_group' => 'measurement',
          ],
        ],
      ],
    ];

    $key_value_store = $this->createMock(KeyValueStoreInterface::class);
    $key_value_store->expects($this->once())
      ->method('get')
      ->with(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY, [])
      ->willReturn($stored_overrides);

    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory->expects($this->once())
      ->method('get')
      ->with(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->willReturn($key_value_store);

    $form = new AttachmentPrototypeFieldOverridesForm(
      $key_value_factory,
      $this->createStub(CacheTagsInvalidatorInterface::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = $form->buildForm([], new FormState());

    $this->assertSame(
      $stored_overrides['additions'],
      json_decode($build['additions_section']['field_additions']['#default_value'], TRUE),
    );
    $this->assertSame(
      $stored_overrides['replacements'],
      json_decode($build['replacements_section']['field_replacements']['#default_value'], TRUE),
    );
  }

  /**
   * Tests that a single addition object is accepted.
   */
  public function testValidateFormAcceptsSingleAdditionObject(): void {
    $form = new AttachmentPrototypeFieldOverridesForm(
      $this->createStub(KeyValueFactoryInterface::class),
      $this->createStub(CacheTagsInvalidatorInterface::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());

    $additions = [
      '1234' => [
        'metric_type' => 'cumulative_reach',
        'field_group' => 'planning',
      ],
    ];
    $form_state = (new FormState())
      ->setValue('field_additions', json_encode($additions))
      ->setValue('field_replacements', '{}');
    $form_array = [];

    $form->validateForm($form_array, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame([
      'additions' => $additions,
      'replacements' => [],
    ], $form_state->getValue('field_overrides_decoded'));
  }

  /**
   * Tests that submitting overrides persists them and invalidates caches.
   */
  public function testSubmitFormInvalidatesOverrideDependentCaches(): void {
    $overrides = [
      'additions' => [
        '1234' => [
          [
            'metric_type' => 'cumulative_reach',
            'field_group' => 'measurement',
          ],
        ],
      ],
    ];

    $key_value_store = $this->createMock(KeyValueStoreInterface::class);
    $key_value_store->expects($this->once())
      ->method('set')
      ->with(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY, $overrides);

    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory->expects($this->once())
      ->method('get')
      ->with(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->willReturn($key_value_store);

    $cache_tags_invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cache_tags_invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([AttachmentPrototype::FIELD_OVERRIDES_CACHE_TAG]);

    $form = new AttachmentPrototypeFieldOverridesForm($key_value_factory, $cache_tags_invalidator);
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($this->createMock(MessengerInterface::class));

    $form_state = (new FormState())->setValue('field_overrides_decoded', $overrides);
    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

}
