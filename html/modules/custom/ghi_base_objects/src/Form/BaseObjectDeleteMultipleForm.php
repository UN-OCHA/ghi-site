<?php

namespace Drupal\ghi_base_objects\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Url;

/**
 * Provides a base object deletion confirmation form.
 *
 * @internal
 */
class BaseObjectDeleteMultipleForm extends DeleteMultipleForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin_content');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletedMessage($count) {
    return $this->formatPlural($count, 'Deleted @count content item.', 'Deleted @count content items.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getInaccessibleMessage($count) {
    return $this->formatPlural($count, "@count base object has not been deleted because you do not have the necessary permissions.", "@count base objects have not been deleted because you do not have the necessary permissions.");
  }

}
