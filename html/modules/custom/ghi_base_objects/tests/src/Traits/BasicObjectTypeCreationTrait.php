<?php

namespace Drupal\Tests\ghi_base_objects\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectType;

/**
 * Provides methods to create a media type from given values.
 *
 * This trait is meant to be used only by test classes.
 */
trait BasicObjectTypeCreationTrait {

  /**
   * Create a base object type.
   *
   * @param mixed[] $values
   *   (optional) Additional values for the media type entity:
   *   - id: The ID of the base object type. If none is provided, a random value
   *     will be used.
   *   - label: The human-readable label of the base object type. If none is
   *     provided, a random value will be used.
   *   - hasYear: Whether the base object type handles years already.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   *   A base object type.
   *
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectType
   */
  protected function createBaseObjectType(array $values = []) {
    $values += [
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'hasYear' => FALSE,
    ];

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $base_object_type = BaseObjectType::create($values);

    $this->assertSame(SAVED_NEW, $base_object_type->save());
    return $base_object_type;
  }

}
