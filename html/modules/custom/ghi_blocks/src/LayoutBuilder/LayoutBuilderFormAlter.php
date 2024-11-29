<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Form\ImportBlockForm;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\layout_builder\Form\AddBlockForm;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\page_manager\Plugin\SectionStorage\PageManagerSectionStorage;

/**
 * Service class for helping with altering the layout builder form.
 */
class LayoutBuilderFormAlter {

  use GinLbModalTrait;
  use StringTranslationTrait;

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
    if ($section_storage instanceof OverridesSectionStorage || $section_storage instanceof PageManagerSectionStorage) {
      $this->makeGinLbForm($form, $form_state);
    }
  }

  /**
   * Alter generic layout builder forms.
   *
   * That is forms for block plugins, that do not inherit from GHIBlockBase.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function alterInlineBlockForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\layout_builder\Form\ConfigureBlockFormBase $form_object */
    $form_object = $form_state->getFormObject();
    $section_storage = $form_object->getSectionStorage();

    // Hide the admin label because it's kind of redundant.
    $form['settings']['admin_label']['#access'] = FALSE;

    // Add a cancel link.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $section_storage->getLayoutBuilderUrl(),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];

    if ($form_state->getBuildInfo()['callback_object'] instanceof AddBlockForm || $form_state->getBuildInfo()['callback_object'] instanceof ImportBlockForm) {
      // For the add block form, make this a link back to the block browser.
      $form['actions']['cancel']['#url'] = Url::fromRoute('layout_builder.choose_block', $this->getRouteMatchParameters(), [
        'query' => array_filter([
          'position' => $this->getCurrentRequest()->query->get('position') ?? NULL,
          'block_category' => $this->getCurrentRequest()->query->get('block_category') ?? NULL,
        ]),
      ]);
      $form['actions']['cancel']['#attributes']['class'][] = 'use-ajax';

    }
  }

  /**
   * Get the current route parameters from routeMatch.
   *
   * @return array
   *   An associative array with the route parameters.
   */
  public static function getRouteMatchParameters() {
    return \Drupal::routeMatch()->getRawParameters()->all();
  }

  /**
   * Get the current request.
   *
   * @return \Symfony\Component\HttpFoundation\Request|null
   *   The request or NULL.
   */
  public static function getCurrentRequest() {
    return \Drupal::requestStack()->getCurrentRequest();
  }

}
