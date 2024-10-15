<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\Form\ConfigureBlockFormBase;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to import a block.
 *
 * @internal
 *   Form classes are internal.
 */
class ImportBlockForm extends ConfigureBlockFormBase {

  use LayoutBuilderHighlightTrait;
  use GinLbModalTrait;

  /**
   * The form steps for the import wizard.
   */
  const STEPS = [
    'import',
    'add_block',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_import_block';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel() {
    return $this->t('Add block');
  }

  /**
   * Builds the form for the block import.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL) {

    $steps = self::STEPS;
    $current_step = $form_state->get('current_import_step') ?? reset($steps);

    switch ($current_step) {
      case 'import':
        $form = $this->importForm($form, $form_state, $section_storage, $delta, $region);
        break;

      case 'add_block':
        $plugin_config = $form_state->get('plugin_config');
        unset($plugin_config['uuid']);
        // Only generate a new component once per form submission.
        if (!$component = $form_state->get('layout_builder__component')) {
          $component = new SectionComponent($this->uuidGenerator->generate(), $region, $plugin_config);
          $plugin = $component->getPlugin();
          if ($plugin instanceof GHIBlockBase && $plugin instanceof ConfigValidationInterface) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $entity = $section_storage->getContextValue('entity');
            $entity_type = $entity->getEntityType();
            $entity_type_label = $entity_type->hasKey('bundle') ? $entity->type->entity->label() : $entity_type->getLabel();
            $plugin->setContext('entity', EntityContext::fromEntity($entity, $entity_type_label));
            $plugin->fixConfigErrors();
            $configuration = $plugin->getConfiguration();
            $component->setConfiguration($configuration);
          }
          $section_storage->getSection($delta)->appendComponent($component);
          $form_state->set('layout_builder__component', $component);
        }
        $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->blockAddHighlightId($delta, $region);
        $form = $this->doBuildForm($form, $form_state, $section_storage, $delta, $component);
        break;
    }

    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * Build the import form where the code can be inserted.
   */
  private function importForm($form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL) {

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__settings'],
      ],
    ];

    $config = $this->getSubmittedConfig($form_state);

    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import from code'),
      '#default_value' => $config,
      '#rows' => 10,
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__actions'],
      ],
    ];
    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate and import'),
    ];
    if ($this->isAjax()) {
      $form['actions']['import']['#ajax']['rebuild'] = TRUE;
      $form['actions']['import']['#ajax']['callback'] = '::ajaxSubmit';
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    // Add a cancel link.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('layout_builder.choose_block', \Drupal::routeMatch()->getRawParameters()->all()),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'dialog-cancel',
          'use-ajax',
        ],
      ],
      '#options' => [
        'query' => [
          'position' => \Drupal::requestStack()->getCurrentRequest()->query->get('position'),
          'block_category' => \Drupal::requestStack()->getCurrentRequest()->query->get('block_category'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * Get a previously submitted plugin configuration from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array|null
   *   The submitted or stored value.
   */
  private function getSubmittedConfig(FormStateInterface $form_state) {
    return $form_state->getValue('config') ?? ($form_state->get('config') ? Yaml::encode($form_state->get('config')) : NULL);
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
    if ($form_state->hasAnyErrors() || $action != 'submit') {
      if ($form_state->hasAnyErrors()) {
        $form['status_messages'] = [
          '#type' => 'status_messages',
          '#weight' => -1000,
        ];
      }
      $form['#sorted'] = FALSE;
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]', $form));
    }
    else {
      $response = $this->successfulAjaxSubmit($form, $form_state);
    }
    return $response;
  }

  /**
   * Validate handler for the import form.
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
    if ($action == 'submit') {
      return parent::validateForm($form, $form_state);
    }
    else {
      $config = $this->getSubmittedConfig($form_state);
      $import_config = Yaml::decode($config);
      $plugin_config = $import_config['conf'];
      $form_state->set('config', $import_config);
      $form_state->set('plugin_config', $plugin_config);

      // Do some very basic validation of the submitted element configuration.
      if ($import_config['element'] != $plugin_config['id']) {
        $form_state->setErrorByName('config', $this->t('Element key does not match the element configuration.'));
      }
      if (empty($import_config['hash'])) {
        $form_state->setErrorByName('config', $this->t('Checksum value is missing.'));
      }
      elseif ($import_config['hash'] != md5($plugin_config['id'] . '|' . Yaml::encode(ArrayHelper::mapObjectsToString($plugin_config)))) {
        $form_state->setErrorByName('config', $this->t('Internal validation failed.'));
      }
    }
  }

  /**
   * Submit handler for the import form.
   *
   * @todo Document what this does.
   */
  public function submitForm(&$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
    if ($action == 'submit') {
      parent::submitForm($form, $form_state);
      /** @var \Drupal\layout_builder_ipe\LayoutBuilder\LayoutBuilderUi $layout_builder_ui */
      $layout_builder_ui = \Drupal::service('layout_builder_ipe.layout_builder_ui');
      $layout_builder_ui->blockFormSubmit($form, $form_state);
      return;
    }

    $steps = self::STEPS;
    $pos = array_search($action, $steps);

    if ($action == 'back') {
      $form_state->set('current_import_step', $steps[$pos - 1] ?? reset($steps));
    }
    else {
      $form_state->set('current_import_step', $steps[$pos + 1] ?? $action);
    }

    $form_state->setRebuild();
  }

}
