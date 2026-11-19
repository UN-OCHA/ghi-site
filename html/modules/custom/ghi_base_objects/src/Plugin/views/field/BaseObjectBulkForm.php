<?php

namespace Drupal\ghi_base_objects\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a base object operations bulk form element.
 *
 * @ViewsField("base_object_bulk_form")
 */
class BaseObjectBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No content selected.');
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    if (empty($this->getBulkOptions())) {
      // Remove the default actions build array.
      unset($form['actions']);
    }
    else {
      parent::viewsForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getBulkOptions($filtered = TRUE) {
    $options = parent::getBulkOptions($filtered);
    if (!$filtered || empty($this->view->result)) {
      return $options;
    }

    foreach (array_keys($options) as $action_id) {
      $action = $this->actions[$action_id];
      $has_access = FALSE;
      foreach ($this->view->result as $row) {
        $entity = $this->getEntity($row);
        if ($entity && $action->getPlugin()->access($entity, $this->view->getUser())) {
          $has_access = TRUE;
          break;
        }
      }
      if (!$has_access) {
        unset($options[$action_id]);
      }
    }

    return $options;
  }

}
