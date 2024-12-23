<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\GlobalPlanOverviewBlockTrait;
use Drupal\ghi_blocks\Traits\HomepageBlockTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Provides a 'KeyFigures' block.
 *
 * @Block(
 *  id = "global_key_figures",
 *  admin_label = @Translation("Key figures"),
 *  category = @Translation("Global"),
 *  data_sources = {
 *    "plans" = "plan_overview_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  },
 *  title = FALSE,
 *  config_forms = {
 *    "key_figures" = {
 *      "title" = @Translation("Key figures"),
 *      "callback" = "keyFiguresForm",
 *      "base_form" = TRUE
 *    }
 *  }
 * )
 */
class KeyFigures extends GHIBlockBase implements MultiStepFormBlockInterface {

  use GlobalPlanOverviewBlockTrait;
  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;
  use HomepageBlockTrait;

  const MAX_ITEMS = 30;

  /**
   * {@inheritdoc}
   */
  public function getData(string $source_key = 'data') {
    $data = parent::getData($source_key);
    $requirements = !empty($data->totals->revisedRequirements) ? $data->totals->revisedRequirements : NULL;
    $funding = !empty($data->totals->totalFunding) ? $data->totals->totalFunding : NULL;
    $funding_progress = CommonHelper::calculateRatio($funding, $requirements);

    // Get the values of people in need and target from the caseload totals.
    $types = [
      'inNeed' => 'In need',
      'target' => 'Targeted',
      'reached' => [
        'type' => 'latestReach',
      ],
      'expectedReach' => 'Expected reach',
    ];
    $plan_query = $this->getPlanQuery();
    $caseload_values = $plan_query->getCaseloadTotalValues($types);
    $affected_countries = $plan_query->getNumerOfGhoCountries();
    $gho_plans = count($plan_query->getGhoPlans());
    return [
      'total_funding' => $funding,
      'total_requirements' => $requirements,
      'funding_progress' => $funding_progress,
      'people_in_need' => $caseload_values['inNeed'],
      'people_target' => $caseload_values['target'],
      'people_reached' => $caseload_values['reached'],
      'people_reached_percent' => CommonHelper::calculateRatio($caseload_values['reached_custom'], $caseload_values['target_custom']),
      'people_expected_reach' => $caseload_values['expectedReach'],
      'countries_affected' => $affected_countries,
      'plans_inside_gho' => $gho_plans,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();

    $items = $this->getConfiguredItems($conf['key_figures']['items']);
    if (empty($items)) {
      return NULL;
    }

    $context = $this->getBlockContext();
    $tree = $this->buildTree($items);
    if (empty($tree)) {
      return NULL;
    }

    $tabs = [];
    foreach ($tree as $group) {
      $rendered = [];
      if (empty($group['children'])) {
        continue;
      }

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $group_item = $this->getItemTypePluginForColumn($group, $context);

      foreach ($group['children'] as $item) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($item, $context);

        $rendered[] = [
          '#type' => 'item',
          '#title' => $item_type->getLabel(),
          0 => $item_type->getRenderArray(),
        ];
      }
      if (empty($rendered)) {
        continue;
      }
      $tabs[] = [
        'title' => [
          '#markup' => $group_item->getLabel(),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $rendered,
          '#attributes' => [
            'class' => ['key-figures'],
          ],
          // This is important to make the template suggestions logic work in
          // common_design_subtheme.theme.
          '#context' => [
            'plugin_type' => 'key_figures',
            'plugin_id' => $this->getPluginId(),
          ],
          '#gin_lb_theme_suggestions' => FALSE,
        ],
      ];
    }

    if (empty($tabs)) {
      return NULL;
    }
    $build = [
      '#theme' => 'tab_container',
      '#tabs' => $tabs,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldDisplayTitle() {
    $this->configuration['label_display'] = FALSE;
    return FALSE;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'key_figures' => [
        'items' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'key_figures';
  }

  /**
   * {@inheritdoc}
   */
  public function keyFiguresForm(array $form, FormStateInterface $form_state) {
    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured key figures'),
      '#title_display' => 'invisible',
      '#description' => $this->t('You can add multiple grouped key figures. Each group will show as a separate tab in the frontend. Items not added to any group will not display in the frontend. A single group will not display in the frontend.'),
      '#item_type_label' => $this->t('Key figure'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'items'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          'api_value' => $this->t('Api value'),
          'custom_value' => $this->t('Custom value'),
          'value_operation' => $this->t('Operation'),
          'render_array' => $this->t('Final value'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#max_items' => self::MAX_ITEMS,
      '#groups' => TRUE,
    ];
    return $form;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'data' => $this->getData('plans'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'item_group' => [],
      'plan_overview_data' => [
        'item_types' => [
          'people_in_need' => [
            'label' => $this->t('People in need'),
          ],
          'people_target' => [
            'label' => $this->t('People targeted'),
          ],
          'people_expected_reach' => [
            'label' => $this->t('People estimated reach'),
            'default_label' => $this->t('People estimated reach'),
          ],
          'people_reached' => [
            'label' => $this->t('People latest reached'),
            'default_label' => $this->t('People reached'),
          ],
          'people_reached_percent' => [
            'label' => $this->t('People latest reached (%)'),
            'default_label' => $this->t('People reached %'),
          ],
          'total_funding' => [
            'label' => $this->t('Total funding'),
            'default_label' => $this->t('Funding (US$)'),
            'global_plan_restrict' => TRUE,
          ],
          'total_requirements' => [
            'label' => $this->t('Total requirements'),
            'default_label' => $this->t('Requirements (US$)'),
            'global_plan_restrict' => TRUE,
          ],
          'funding_progress' => [
            'label' => $this->t('Funding Coverage'),
            'default_label' => $this->t('% Funded'),
            'global_plan_restrict' => TRUE,
          ],
          'countries_affected' => [
            'label' => $this->t('Countries affected'),
          ],
          'plans_inside_gho' => [
            'label' => $this->t('Number of plans'),
          ],
        ],
      ],
      'label_value' => [
        'footnote' => TRUE,
        'custom_logo' => TRUE,
      ],
    ];
    return $item_types;
  }

}
