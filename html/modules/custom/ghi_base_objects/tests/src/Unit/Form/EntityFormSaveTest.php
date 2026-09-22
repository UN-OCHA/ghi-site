<?php

namespace Drupal\Tests\ghi_base_objects\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Form\BaseObjectForm;
use Drupal\ghi_base_objects\Form\BaseObjectTypeForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests base object form save results and redirects.
 *
 * @group ghi_base_objects
 */
class EntityFormSaveTest extends UnitTestCase {

  /**
   * Tests that forms return the storage result without changing redirects.
   */
  #[DataProvider('saveProvider')]
  public function testSave(bool $bundle_form, bool $new): void {
    require_once $this->root . '/core/includes/common.inc';
    $status = $new ? SAVED_NEW : SAVED_UPDATED;
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('save')->willReturn($status);
    $entity->method('label')->willReturn('Example');
    $entity->method('id')->willReturn('example');
    $entity->method('toUrl')->with('collection')->willReturn(new Url('entity.base_object_type.collection'));

    $form = $bundle_form ? new BaseObjectTypeForm() : new BaseObjectForm(
      $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $form->setEntity($entity);
    $form->setStringTranslation($this->getStringTranslationStub());
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addMessage');
    $form->setMessenger($messenger);
    $form_state = new FormState();

    $this->assertSame($status, $form->save([], $form_state));
    $route = $bundle_form ? 'entity.base_object_type.collection' : 'entity.base_object.canonical';
    $this->assertSame($route, $form_state->getRedirect()->getRouteName());
  }

  /**
   * Provides both forms with newly created and updated entities.
   */
  public static function saveProvider(): array {
    return [
      'new object' => [FALSE, TRUE],
      'updated object' => [FALSE, FALSE],
      'new bundle' => [TRUE, TRUE],
      'updated bundle' => [TRUE, FALSE],
    ];
  }

}
