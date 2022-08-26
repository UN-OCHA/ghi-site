<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Controller class for ajax interactions on blocks in GHI.
 */
class ShowBlockConfigForm extends FormBase {

  use GinLbModalTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_show_block_config_block';
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $plugin = $component->getPlugin();
    if (!$plugin instanceof GHIBlockBase && !$plugin instanceof InlineBlock) {
      throw new \InvalidArgumentException(sprintf('Unsupported plugin type "%s".', $plugin->getPluginId()));
    }

    $form['#title'] = $this->t('Block configuration for @plugin', [
      '@plugin' => $plugin->getPluginDefinition()['admin_label'],
    ]);

    $plugin_configuration = $plugin->getConfiguration();
    unset($plugin_configuration['uuid']);
    unset($plugin_configuration['context_mapping']);
    unset($plugin_configuration['data_sources']);

    $config_export = Yaml::encode(ArrayHelper::mapObjectsToString([
      'element' => $plugin->getPluginId(),
      'conf' => $plugin_configuration,
      'hash' => md5($plugin->getPluginId() . '|' . Yaml::encode(ArrayHelper::mapObjectsToString($plugin_configuration))),
    ]));

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__settings'],
      ],
    ];
    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration export'),
      '#value' => $config_export,
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
