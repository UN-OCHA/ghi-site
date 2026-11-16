<?php

namespace Drupal\ghi_blocks_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;

/**
 * Exercises an item editor whose value and render-array paths differ.
 */
class CallToActionItemTestForm extends FormBase {

  /**
   * The nested value path used by the configuration-container item editor.
   */
  public const CONFIG_PARENTS = ['call_to_action', 'items', 'item_config', 'plugin_config'];

  /**
   * Creates the form with an existing item configuration.
   */
  public function __construct(protected array $configuration, protected ConfigurationContainerItemManager $itemManager) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'call_to_action_item_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['#parents'] = [];
    $form['#array_parents'] = [];
    $form['editor']['plugin_config'] = [
      '#type' => 'container',
      '#parents' => self::CONFIG_PARENTS,
      '#array_parents' => ['editor', 'plugin_config'],
    ];
    $item = $this->itemManager->createInstance('call_to_action');
    $item->setConfig($this->configuration);
    $subform_state = SubformState::createForSubform($form['editor']['plugin_config'], $form, $form_state);
    $form['editor']['plugin_config'] = $item->buildForm($form['editor']['plugin_config'], $subform_state);
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Save CTA'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('saved_cta', $form_state->getValue(self::CONFIG_PARENTS));
  }

}
