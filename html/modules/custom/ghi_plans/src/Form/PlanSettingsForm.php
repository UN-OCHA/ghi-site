<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Form with examples on how to use batch api.
 */
class PlanSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_plans_plan_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#parents'] = [];
    $form['#node'] = $node;

    // Define which node fields we want to make available here, and to which
    // vertical tab group they should be added.
    $fields = [
      'field_plan_short_name'             => 'plan_data',
      'field_plan_version_argument'       => 'plan_data',
      'field_plan_include_homepage'       => 'plan_display',
      'field_plan_level_attachment_id'    => 'plan_display',
      'field_related_ocha_pages'          => 'plan_display',
      'field_plan_hide_ple_counts'        => 'plan_display',
      'field_plan_hide_gve_counts'        => 'plan_display',
      'field_plan_prevent_fts_link'       => 'plan_display',
      'field_max_admin_level'             => 'plan_display',
      'field_decimal_format'              => 'plan_display',
      'field_plan_footnote_inneed'        => 'plan_footnotes',
      'field_plan_footnote_target'        => 'plan_footnotes',
      'field_plan_footnote_requirements'  => 'plan_footnotes',
    ];
    $form_state->set('fields', $fields);

    // Define the tabs and set the default one. If an editor submits the form,
    // we want that to see the same page as before the submission to make it
    // easier to keep the context.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => $form_state->has('active_tab') ? $form_state->get('active_tab') : 'edit-plan-data',
    ];
    $form['plan_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Data'),
      '#collapsible' => TRUE,
      '#group' => 'tabs',
    ];
    $form['plan_display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#group' => 'tabs',
    ];
    $form['plan_footnotes'] = [
      '#type' => 'details',
      '#title' => $this->t('Footnotes'),
      '#collapsible' => TRUE,
      '#group' => 'tabs',
    ];

    $form_display = EntityFormDisplay::collectRenderDisplay($node, 'default');

    // Now go over the fields and get the widgets for each one, adding it to
    // the respective tab group.
    foreach ($fields as $field_name => $group) {
      if (!$node->hasField($field_name)) {
        continue;
      }
      $widget = $form_display->getRenderer($field_name);
      $items = $node->get($field_name);
      $items->filterEmptyItems();

      $form[$group][$field_name] = $widget->form($items, $form, $form_state);

      // @codingStandardsIgnoreStart
      // if ($field_name == 'field_plan_level_attachment_id') {
      //   // Provide a custom select element offering the available plan level
      //   // caseload attachments.
      //   $plan_id = hpc_api_data_field_data('node', $plan_node, 'field_original_id', 0, 'value');
      //   // Get all attachments.
      //   $attachments = hpc_api_data_get_attachments('plan', $plan_id);
      //   // Filter down to caseloads.
      //   $caseloads = array_filter($attachments, function ($item) {
      //     return strtolower($item->type) == 'caseload';
      //   });
      //   // Sort by custom reference.
      //   usort($caseloads, function($a, $b) {
      //     return $a->attachmentVersion->customReference >= $b->attachmentVersion->customReference;
      //   });
      //   // Build the options array, keyed by the attachment id for internal
      //   // storage, constructing the label from composed reference and
      //   // description, adding the attachment id for clarity.
      //   $caseload_options = array_combine(
      //     array_map(function ($item) {
      //       return (string) $item->id;
      //     }, $caseloads),
      //     array_map(function ($item) {
      //       return (string) $item->composedReference . ': ' . $item->attachmentVersion->value->description . ' (' . $item->id . ')';
      //     }, $caseloads)
      //   );
      //   // Now build the actual select element.
      //   $form[$group][$field_name . '_custom_select'] = array(
      //     '#type' => 'select',
      //     '#title' => $form[$group][$field_name][LANGUAGE_NONE][0]['value']['#title'],
      //     '#description' => $form[$group][$field_name][LANGUAGE_NONE][0]['value']['#description'],
      //     '#options' => array(0 => $this->t('Automatic')) + $caseload_options,
      //     '#default_value' => array($form[$group][$field_name][LANGUAGE_NONE][0]['value']['#default_value']),
      //     '#states' => array(
      //       'visible' => array(
      //         ':input[name="field_plan_include_homepage[und]"]' => array('checked' => TRUE),
      //       ),
      //     ),
      //     '#weight' => $form[$group][$field_name]['#weight'],
      //   );
      //   // And hide the original field widget.
      //   $form[$group][$field_name][LANGUAGE_NONE][0]['#access'] = FALSE;
      // }
      // @codingStandardsIgnoreEnd

      // The related pages should only show if they are actually enabled
      // site-wide.
      // @todo Enable once ready.
      if ($field_name == 'field_related_ocha_pages') {
        $form[$group][$field_name]['#access'] = FALSE;
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#weight' => 10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form['#node'];
    $form_state->set('active_tab', $form_state->getValue('tabs__active_tab'));
    $form_state->setRebuild();

    $fields = $form_state->get('fields');
    foreach (array_keys($fields) as $field_name) {
      $node->set($field_name, $form_state->getValue($field_name));
    }
    $node->save();

    $this->messenger()->addStatus($this->t('The settings have been saved'));
  }

}
