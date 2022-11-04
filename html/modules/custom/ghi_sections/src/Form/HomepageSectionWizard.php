<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a wizard form for creating global section nodes.
 */
class HomepageSectionWizard extends GlobalSectionWizard {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_homepage_sections_wizard';
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundle() {
    return 'homepage';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = array_intersect_key($form_state->getValues(), array_flip([
      'year',
      'tags',
      'team',
      'title',
    ]));

    $action = self::getActionFromFormState($form_state);

    if ($action != 'back' && $form_state->get('step') == 0) {
      $properties = [
        'type' => 'homepage',
        'field_year' => $values['year'],
      ];
      $sections = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
      if (count($sections)) {
        $form_state->setErrorByName('year', $this->t('A homepage for <em>@year</em> already exists.', [
          '@year' => $values['year'],
        ]));
      }
    }
  }

}
