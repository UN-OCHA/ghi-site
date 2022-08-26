<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\ghi_sections\Entity\Homepage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Global settings form.
 */
class GlobalSettingsForm extends ConfigFormBase {

  use GlobalSettingsTrait;
  use VerticalTabsTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a document create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

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
    $homepage_years = $this->getHomepageYears();
    if (!$homepage_years) {
      return $form;
    }

    // Define the tabs and set the default one. If an editor submits the form,
    // we want them to see the same page as before the submission to make it
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
      if ($year_config != $config->get($year) && $homepage = $this->getHomepageForYear($year)) {
        // Also invalidate the cache for this specific homepage.
        Cache::invalidateTags($homepage->getCacheTags());
      }
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
   * @return array|null
   *   An array of years as keys and values.
   */
  public function getHomepageYears() {
    $years = [];
    $properties = [
      'type' => 'homepage',
    ];
    $homepages = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
    if (empty($homepages)) {
      return NULL;
    }
    $years = array_map(function (Homepage $homepage) {
      return $homepage->getYear();
    }, $homepages);
    asort($years);
    $years = array_reverse($years, TRUE);
    return array_combine($years, $years);
  }

  /**
   * Get the homepage node for the given year.
   *
   * @param int $year
   *   The year.
   *
   * @return \Drupal\ghi_sections\Entity\Homepage
   *   The homepage node object.
   */
  public function getHomepageForYear($year) {
    $properties = [
      'type' => 'homepage',
      'field_year' => $year,
    ];
    $homepages = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
    return count($homepages) == 1 ? reset($homepages) : NULL;
  }

}
