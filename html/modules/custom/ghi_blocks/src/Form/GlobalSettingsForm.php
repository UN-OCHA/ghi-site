<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\hpc_common\Plugin\Condition\PageParameterCondition;
use Drupal\page_manager\Entity\Page;
use Drupal\page_manager_publishable_variants\Plugin\Condition\VariantPublishedCondition;

/**
 * Global settings form.
 */
class GlobalSettingsForm extends ConfigFormBase {

  use GlobalSettingsTrait;
  use VerticalTabsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_global_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->getConfigKey(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $homepage_years = $this->getHomepageYears(FALSE);

    // Define the tabs and set the default one. If an editor submits the form,
    // we want that to see the same page as before the submission to make it
    // easier to keep the context.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['years'] = [
      '#tree' => TRUE,
    ];

    foreach ($homepage_years as $year) {
      $year_config = $this->getYearConfig($year);

      $form['years'][$year] = [
        '#type' => 'details',
        '#title' => $year,
        '#tree' => TRUE,
        '#group' => 'tabs',
      ];

      foreach ($this->getCheckboxOptions() as $key => $def) {
        $form['years'][$year][$key] = [
          '#type' => 'checkbox',
          '#default_value' => $year_config[$key] ?? NULL,
        ] + $def;
      }

      foreach ($this->getDisabledCheckboxes() as $key) {
        $form['years'][$year][$key]['#disabled'] = TRUE;
        $form['years'][$year][$key]['#description'] .= '<br />' . $this->t('Note: This field is disabled because the underlying feature has not been implemented yet.');
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $this->processVerticalTabs($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $year_values = $form_state->getValue('years');
    $config = $this->config($this->getConfigKey());
    foreach ($year_values as $year => $year_config) {
      $config->set($year, $year_config);
      $config->save();
    }
    $this->processVerticalTabsSubmit($form, $form_state);
    $form_state->setRebuild();
    return parent::submitForm($form, $form_state);
  }

  /**
   * Get the available homepage years.
   *
   * @param bool $published_only
   *   Whether to include only published years.
   *
   * @return array
   *   An array of years as keys and values.
   */
  public function getHomepageYears($published_only = TRUE) {
    $years = [];
    $homepage = Page::load('homepage');

    /** @var Drupal\page_manager_publishable_variants\Plugin\Condition\VariantPublishedCondition $access_condition */
    $published_variants_condition = NULL;
    foreach ($homepage->getAccessConditions() as $access_condition) {
      if (!$access_condition instanceof VariantPublishedCondition) {
        continue;
      }
      $published_variants_condition = $access_condition;
    }

    foreach ($homepage->getVariants() as $variant) {
      if ($published_only && $published_variants_condition && !$published_variants_condition->hasAccess($variant->id())) {
        continue;
      }

      $plugin_collection = $variant->getPluginCollections();

      $selection_criteria = $plugin_collection['selection_criteria'];
      foreach ($selection_criteria as $selection_criteria) {
        if (!$selection_criteria instanceof PageParameterCondition) {
          continue;
        }
        /** @var \Drupal\hpc_common\Plugin\Condition\PageParameterCondition $selection_criteria */
        $configuration = $selection_criteria->getConfiguration();
        if ($configuration['parameter'] == 'year') {
          // Gotcha.
          $years[] = $configuration['value'];
        }
      }
    }
    asort($years);
    $years = array_reverse($years, TRUE);
    return array_combine($years, $years);
  }

}
