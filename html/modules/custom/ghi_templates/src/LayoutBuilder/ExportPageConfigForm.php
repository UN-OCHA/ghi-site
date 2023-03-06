<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Form for exporting page config.
 */
class ExportPageConfigForm extends FormBase {

  use GinLbModalTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_export_page_config';
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL) {
    $config_export = [
      'page_config' => [],
    ];
    foreach ($section_storage->getSections() as $delta => $section) {
      $config_export['page_config'][$delta] = $section->toArray();
      uasort($config_export['page_config'][$delta]['components'], function ($a, $b) {
        return $a['weight'] <=> $b['weight'];
      });
      foreach ($config_export['page_config'][$delta]['components'] as &$component) {
        unset($component['configuration']['uuid']);
        unset($component['configuration']['context_mapping']);
        unset($component['configuration']['data_sources']);
      }
    }

    $config_export['hash'] = md5(Yaml::encode(ArrayHelper::mapObjectsToString($config_export['page_config'])));

    $form['#title'] = $this->t('Export page configuration');

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__settings'],
      ],
    ];
    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration export'),
      '#value' => Yaml::encode(ArrayHelper::mapObjectsToString($config_export)),
      '#rows' => 20,
      '#disabled' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__actions'],
      ],
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

    $form['#attached']['library'][] = 'ghi_blocks/layout_builder_modal_admin';
    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * Submit callback for the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing needed.
  }

}
