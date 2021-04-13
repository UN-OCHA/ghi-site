<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'PlanEntityTypes' block.
 *
 * @Block(
 *  id = "plan_entity_types",
 *  admin_label = @Translation("Plan: Entity types"),
 *  category = @Translation("Plans"),
 *  data_sources = {
 *    "data" = {
 *      "arguments" = {
 *        "endpoint" = "public/plan/{plan_id}?content=basic&addPercentageOfTotalTarget=true&version=current",
 *        "api_version" = "v2",
 *      }
 *    }
 *  },
 *  field_context_mapping = {
 *    "year" = "field_plan_year",
 *    "plan_id" = "field_original_id"
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Plan node"))
 *  }
 * )
 */
class PlanEntityTypes extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $data = $this->getData();
    if (empty($data)) {
      return '';
    }

    return [
      '#type' => 'markup',
      '#markup' => Markup::create('<pre>' . print_r($this->configuration['hpc'], TRUE) . '</pre>'),
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    return [
      'hpc' => [
        'basic' => ['input_1' => NULL],
        'sidebar_items' => ['input_2' => NULL],
        'another_form' => ['input_3' => NULL],
      ],
    ] + parent::baseConfigurationDefaults();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return [
      'basic' => 'basicConfigForm',
      'sidebar_items' => 'sidebarItemsForm',
      'another_form' => 'anotherForm',
    ];
  }

  /**
   * Form builder for the basic config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function basicConfigForm(array $form, FormStateInterface $form_state) {
    $form['input_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Input 1'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'input_1'),
    ];
    return $form;
  }

  /**
   * Form builder for the basic config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function sidebarItemsForm(array $form, FormStateInterface $form_state) {
    $form['input_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Input 2'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'input_2'),
    ];
    return $form;
  }

  /**
   * Form builder for the basic config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function anotherForm(array $form, FormStateInterface $form_state) {
    $form['input_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Input 3'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'input_3'),
    ];
    return $form;
  }

}
