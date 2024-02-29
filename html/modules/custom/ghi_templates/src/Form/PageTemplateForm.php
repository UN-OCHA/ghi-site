<?php

namespace Drupal\ghi_templates\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Form handler for the page template edit forms.
 *
 * @internal
 */
class PageTemplateForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    // Allow to preset the source via a GET parameter.
    $source = $this->getRequest()->query->get('source');
    if (!empty($source) && strpos($source, ':')) {
      [$entity_type_id, $entity_id] = explode(':', $source);
      $source_entity = $this->entityTypeManager->getStorage($entity_type_id)?->load($entity_id);
      if ($source_entity) {
        $form['field_entity_reference']['widget'][0]['target_id']['#default_value'] = $source_entity;
        $form['field_entity_reference']['widget']['#disabled'] = TRUE;
      }
    }

    if (!$entity->isNew()) {
      // Disable the source page form element for existing page templates.
      $form['field_entity_reference']['widget']['#disabled'] = TRUE;
    }

    if ($entity->isNew()) {
      $form['field_base_objects']['widget']['#access'] = FALSE;
    }
    else {
      // Disable the base objects form element on all forms (add / edit).
      $form['field_base_objects']['widget']['#disabled'] = TRUE;
      $form['field_base_objects']['widget']['add']['#access'] = FALSE;
      if (!empty($form['field_base_objects']['widget']['list'])) {
        unset($form['field_base_objects']['widget']['list']['#tabledrag']);
        unset($form['field_base_objects']['widget']['list']['#header'][3]);
        unset($form['field_base_objects']['widget']['list']['#header'][4]);
        $object_list = $form['field_base_objects']['widget']['list'] ?? [];
        foreach (Element::children($object_list) as $key) {
          unset($form['field_base_objects']['widget']['list'][$key]['remove']);
          unset($form['field_base_objects']['widget']['list'][$key]['weight']);
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    if ($this->entity->isNew()) {
      $actions['submit']['#value'] = $this->t('Create page template');
    }
    else {
      $actions['submit']['#value'] = $this->t('Update page template');
    }
    return $actions;
  }

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
