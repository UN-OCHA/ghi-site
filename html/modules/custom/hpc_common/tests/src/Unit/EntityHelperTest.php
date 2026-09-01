<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\hpc_common\Helpers\EntityHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @covers Drupal\hpc_common\Helpers\EntityHelper
 */
class EntityHelperTest extends UnitTestCase {

  /**
   * Test getting an original id from an entity.
   *
   * @group EntityHelper
   */
  public function testGetOriginalIdFromEntity() {
    // Test behavior if field is not empty.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([123]);
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->hasField('field_original_id')->willReturn(TRUE);
    $entity->get('field_original_id')->willReturn($field->reveal());
    $this->assertEquals(123, EntityHelper::getOriginalIdFromEntity($entity->reveal()));

    // Test behavior if field doesn't exist.
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->hasField('field_original_id')->willReturn(FALSE);
    $this->assertNull(EntityHelper::getOriginalIdFromEntity($entity->reveal()));

    // Test behavior if field is empty.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([]);
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->hasField('field_original_id')->willReturn(TRUE);
    $entity->get('field_original_id')->willReturn($field->reveal());
    $this->assertNull(EntityHelper::getOriginalIdFromEntity($entity->reveal()));
  }

}
