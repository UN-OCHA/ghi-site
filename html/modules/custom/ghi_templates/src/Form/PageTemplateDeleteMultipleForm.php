<?php

namespace Drupal\ghi_templates\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Url;

/**
 * Provides a page template deletion confirmation form.
 *
 * This is necessary to provide the entity:delete_action:page_template action
 * plugin.
 *
 * @internal
 */
class PageTemplateDeleteMultipleForm extends DeleteMultipleForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.page_template.collection');
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
    return $this->formatPlural($count, "@count page template has not been deleted because you do not have the necessary permissions.", "@count page templates have not been deleted because you do not have the necessary permissions.");
  }

}
