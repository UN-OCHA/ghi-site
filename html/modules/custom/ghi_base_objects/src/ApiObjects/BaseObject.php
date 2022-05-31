<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Base class for API base objects.
 */
abstract class BaseObject extends ApiObjectBase implements BaseObjectInterface {

  /**
   * Get the corresponding bundle.
   *
   * By default, this just takes the lowercased class name. API object classes
   * can override this function to provide the correct bundle of a base object
   * entity.
   *
   * @return string
   *   The bundle name of the base object entity
   */
  public function getBundle() {
    $called_class = get_called_class();
    $namespace_parts = explode('\\', $called_class);
    return strtolower(array_pop($namespace_parts));
  }

  /**
   * Get the base object entity corresponding to this API object.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The base object entity.
   */
  public function getEntity() {
    return BaseObjectHelper::getBaseObjectFromOriginalId($this->id(), $this->getBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getName($shortname = FALSE) {
    if ($shortname && $entity = $this->getEntity()) {
      // Let's see if this object has a shortname.
      if ($entity->hasField('field_short_name') && $entity->get('field_short_name')->value) {
        return $entity->get('field_short_name')->value;
      }
    }
    return $this->name ?? ($this->getRawData()->name ?? NULL);
  }

}
