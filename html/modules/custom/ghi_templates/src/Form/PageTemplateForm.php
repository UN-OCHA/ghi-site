<?php

namespace Drupal\ghi_templates\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the page template edit forms.
 *
 * @internal
 */
class PageTemplateForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $entity = $this->entity;
    if ($entity->id()) {
      if ($entity->access('view')) {
        $form_state->setRedirect(
          'entity.page_template.canonical',
          ['page_template' => $entity->id()]
        );
      }
      else {
        $form_state->setRedirect('entity.page_template.collection');
      }
    }
    else {
      // In the unlikely case something went wrong on save, the entity will be
      // rebuilt and the form redisplayed the same way as in preview.
      $this->messenger()->addError($this->t('The page template could not be saved.'));
      $form_state->setRebuild();
    }
  }

}
