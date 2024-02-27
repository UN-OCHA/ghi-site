<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_templates\PageConfigTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Form for exporting page config.
 */
class ExportPageConfigForm extends TemplateFormBase {

  use LayoutEntityHelperTrait;
  use PageConfigTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_export_page_config';
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, SectionStorageInterface $section_storage = NULL) {
    $form = parent::buildForm($form, $form_state, $entity, $section_storage);

    $config_export = $this->exportSectionStorage($section_storage);

    $form['#title'] = $this->t('Export page configuration for @label', [
      '@label' => $section_storage->label(),
    ]);

    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration export'),
      '#value' => Yaml::encode(ArrayHelper::mapObjectsToString($config_export)),
      '#rows' => 20,
      '#disabled' => TRUE,
    ];

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

    return $form;
  }

  /**
   * Submit callback for the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing needed.
  }

}
