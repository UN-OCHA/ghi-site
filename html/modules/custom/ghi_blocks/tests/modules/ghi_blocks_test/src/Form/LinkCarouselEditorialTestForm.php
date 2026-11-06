<?php

namespace Drupal\ghi_blocks_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;

/**
 * Provides a cacheable form using Layout Builder's plugin form callbacks.
 */
class LinkCarouselEditorialTestForm extends FormBase {

  /**
   * Creates an editorial form for the configured block.
   */
  public function __construct(private LinkCarousel $block) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'link_carousel_editorial_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $this->block->buildConfigurationForm($form['settings'], $subform_state);
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Save editorial content'];
    $form['#validate'] = [[$this, 'validateForm']];
    $form['#submit'] = [[$this, 'submitForm']];
    $this->block->blockFormAlter($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->block->validateConfigurationForm($form['settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->block->submitConfigurationForm($form, $subform_state);
  }

}
