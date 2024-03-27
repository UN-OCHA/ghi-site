<?php

namespace Drupal\ghi_templates\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a page template operations bulk form element.
 *
 * @ViewsField("page_template_bulk_form")
 */
class PageTemplateBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No content selected.');
  }

}
