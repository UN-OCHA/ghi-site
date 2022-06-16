<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Service class for helping with altering the layout builder form.
 */
class LayoutBuilderFormAlter {

  use GinLbModalTrait;

  /**
   * Alter the confirmation form for the "Discard changes" button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function alterConfirmationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = reset($form_state->getBuildInfo()['args']);
    if ($section_storage instanceof OverridesSectionStorage) {
      $this->makeGinLbForm($form, $form_state);
    }
  }

}
