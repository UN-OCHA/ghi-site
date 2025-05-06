<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_blocks\Traits\TableTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Traits\RenderArrayTrait;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanEntityTypes' block.
 *
 * @Block(
 *  id = "plan_caseload_trends_table",
 *  admin_label = @Translation("Caseload Trends Table"),
 *  category = @Translation("Plan elements"),
 *  default_title = @Translation("Evolution of the humanitarian response"),
 *  data_sources = {
 *    "attachment_search" = "attachment_search_query",
 *    "plan_funding" = "flow_search_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  }
 * )
 */
class PlanCaseloadTrendsTable extends GHIBlockBase implements OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use PlanFootnoteTrait;
  use RenderArrayTrait;
  use TableTrait;
  use TableSoftLimitTrait;

  const DEFAULT_MAX_YEARS = 10;

  /**
   * The plan manager.
   *
   * @var \Drupal\ghi_plans\PlanManager
   */
  protected $planManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanClusterLogframeLinks $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planManager = $container->get('ghi_plans.manager');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    $title = parent::getDefaultTitle();
    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    // @codingStandardsIgnoreStart
    return $title ? $this->t((string) $title, [], ['langcode' => $langcode]) : $title;
    // @codingStandardsIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table = $this->buildTableData();
    if (empty($table)) {
      return NULL;
    }
    return [
      '#theme' => 'table',
      '#header' => $table['header'],
      '#rows' => $table['rows'],
      '#progress_groups' => TRUE,
      '#sortable' => TRUE,
      '#soft_limit' => $this->getBlockConfig()['soft_limit'],
    ];
  }

  /**
   * Build the table data for this element.
   *
   * @return array|null
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData() {
    $data = $this->buildSourceData();
    if (empty($data)) {
      return NULL;
    }

    $plan_object = $this->getCurrentPlanObject();
    $langcode = $plan_object?->getPlanLanguage() ?? 'en';
    $t_options = ['langcode' => $langcode];

    $header = [
      'year' => $this->buildHeaderColumn($this->t('Year', [], $t_options), 'number'),
      'plan_type' => $this->t('Type'),
      'in_need' => $this->buildHeaderColumn($this->t('People in need', [], $t_options), 'amount'),
      'target' => $this->buildHeaderColumn($this->t('People targeted', [], $t_options), 'amount'),
      'target_percent' => $this->buildHeaderColumn($this->t('People targeted (%)', [], $t_options), 'amount'),
      'reached' => $this->buildHeaderColumn($this->t('People reached', [], $t_options), 'amount'),
      'reached_percent' => $this->buildHeaderColumn($this->t('People reached (%)', [], $t_options), 'amount'),
      'requirements' => $this->buildHeaderColumn($this->t('Requirements ($)', [], $t_options), 'currency'),
      'funding' => $this->buildHeaderColumn($this->t('Funding ($)', [], $t_options), 'currency'),
      'coverage' => $this->buildHeaderColumn($this->t('% Funded', [], $t_options), 'percentage'),
    ];
    $rows = [];

    $theme_options = [
      'decimals' => 1,
      'decimal_format' => $plan_object?->getDecimalFormat(),
    ];

    foreach ($data as $item) {
      $row = [
        'year' => [
          'data' => $item['year'],
          'data-raw-value' => $item['year'],
          'data-column-type' => 'string',
        ],
        'plan_type' => [
          'data' => array_filter([
            $item['plan_type_link'],
            $item['plan_type_tooltip'] ? [
              '#theme' => 'hpc_tooltip',
              '#tooltip' => $this->t('This plan is not included in the GHO totals'),
              '#class' => 'gho-included-tooltip',
              '#tag_content' => [
                '#theme' => 'hpc_icon',
                '#icon' => 'warning',
                '#tag' => 'span',
              ],
            ] : NULL,
          ]),
          'data-raw-value' => $item['plan_type'],
          'data-column-type' => 'string',
        ],
        'in_need' => [
          'data' => [
            $this->buildRenderArray('hpc_amount', $item['in_need'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'in_need'),
          ],
          'data-raw-value' => $item['in_need'] ?? 0,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_value' => $item['in_need'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'in_need'),
        ],
        'target' => [
          'data' => [
            $this->buildRenderArray('hpc_amount', $item['target'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'target'),
          ],
          'data-raw-value' => $item['target'] ?? 0,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_value' => $item['target'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'target'),
        ],
        'target_percent' => [
          'data' => $this->buildRenderArray('hpc_percent', $item['target_percent'] ?? FALSE, $theme_options),
          'data-raw-value' => $item['target_percent'] ?? 0,
          'data-column-type' => 'amount',
          'data-progress-group' => 'target_percent',
          'export_value' => $item['target_percent'],
        ],
        'reached' => [
          'data' => $this->buildRenderArray('hpc_amount', $item['reached'] ?? FALSE, $theme_options),
          'data-raw-value' => $item['reached'] ?? 0,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_value' => $item['reached'],
        ],
        'reached_percent' => [
          'data' => $this->buildRenderArray('hpc_percent', $item['reached_percent'] ?? FALSE, $theme_options),
          'data-raw-value' => $item['reached_percent'] ?? 0,
          'data-column-type' => 'amount',
          'data-progress-group' => 'reached_percent',
          'export_value' => $item['reached_percent'],
        ],
        'requirements' => [
          'data' => [
            $this->buildRenderArray('hpc_currency', $item['requirements'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'requirements'),
          ],
          'data-raw-value' => $item['requirements'] ?? 0,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
          'export_value' => $item['requirements'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'requirements'),
        ],
        'funding' => [
          'data' => [
            $this->buildRenderArray('hpc_currency', $item['funding'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'funding'),
          ],
          'data-raw-value' => $item['funding'] ?? 0,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
          'export_value' => $item['funding'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'funding'),
        ],
        'coverage' => [
          'data' => $this->buildRenderArray('hpc_percent', $item['coverage'] ?? FALSE, $theme_options),
          'data-raw-value' => $item['coverage'] ?? 0,
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
          'export_value' => $item['coverage'],
        ],
      ];
      $rows[] = $row;
    }

    if (empty($rows)) {
      return NULL;
    }

    $columns = $this->getBlockConfig()['columns'];
    if (!empty(array_filter($columns))) {
      foreach ($columns as $key => $enabled) {
        if ($enabled) {
          continue;
        }
        unset($header[$key]);
        foreach ($rows as &$row) {
          unset($row[$key]);
        }
      }
    }

    return [
      'header' => $header,
      'rows' => $rows,
    ];
  }

  /**
   * Build the source data for this element.
   *
   * @return array|null
   *   An array with data or NULL.
   */
  private function buildSourceData() {
    $related_plans = $this->getRelatedPlans();
    if (empty($related_plans)) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $attachments_query */
    $attachments_query = $this->getQueryHandler('attachment_search');

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery $funding_query */
    $funding_query = $this->getQueryHandler('plan_funding');

    // Filter out plans without a plan type.
    $related_plans = array_filter($related_plans, function (Plan $plan) {
      return $plan->getPlanType() !== NULL;
    });

    // Extract the plan ids and get the financial data per plan in one go using
    // the flow search endpoint.
    $plan_ids = array_map(function ($plan) {
      return $plan->getSourceId();
    }, $related_plans);
    $plan_funding_data = $funding_query->getFinancialDataPerPlan($plan_ids);

    // Collect the plan types per year to see if we need to add information to
    // distinguish different plans in the same year.
    $plan_data = [];
    $plan_types = [];
    foreach ($related_plans as $plan) {
      $plan_year = $plan->getYear();
      $plan_type = $plan->getPlanType()->getAbbreviation();
      $plan_types[$plan_year][$plan_type] = !empty($plan_types[$plan_year][$plan_type]) ? $plan_types[$plan_year][$plan_type] + 1 : 1;
    }

    foreach ($related_plans as $plan) {
      $plan_year = $plan->getYear();
      $plan_type = $plan->getPlanType()->getAbbreviation();

      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment[] $caseloads */
      $caseloads = $attachments_query->getAttachmentsByObject('plan', $plan->getSourceId(), ['type' => 'caseload']);
      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $caseload */
      $caseload = count($caseloads) > 1 ? $plan->getPlanCaseload($caseloads) : (!empty($caseloads) ? reset($caseloads) : NULL);
      $funding_data = $plan_funding_data[$plan->getSourceId()];

      // Setup the plan type label to distinguish years with multiple plans of
      // the same type.
      $plan_type_label = $plan_types[$plan_year][$plan_type] > 1 ? $plan_type . ' - ' . $plan->getShortName() : $plan_type;

      $in_need = $caseload?->getFieldByType('inNeed')?->value;
      $target = $caseload?->getFieldByType('target')?->value;
      $reached = $caseload?->getCaseloadValue('latestReach');

      // See if there is a section for this plan.
      $section = $this->sectionManager->loadSectionForBaseObject($plan);

      $plan_data[] = [
        'year' => $plan_year,
        'plan_type' => $plan_type_label,
        'plan_type_link' => $section && $section->access('view') ? $section->toLink($plan_type_label)->toRenderable() : ['#markup' => $plan_type_label],
        'plan_type_tooltip' => !$plan->isPartOfGho(),
        'in_need' => $in_need,
        'target' => $target,
        'target_percent' => $target ? CommonHelper::calculateRatio($target, $in_need) * 100 : NULL,
        'reached' => $reached,
        'reached_percent' => $reached ? CommonHelper::calculateRatio($reached, $target) * 100 : NULL,
        'requirements' => $funding_data['current_requirements'],
        'funding' => $funding_data['total_funding'],
        'coverage' => $funding_data['funding_coverage'],
        'footnotes' => $plan ? $this->getFootnotesForPlanBaseobject($plan) : NULL,
      ];
    }

    // Now fill in missing years.
    $years = array_unique(array_map(function ($item) {
      return $item['year'];
    }, $plan_data));
    $range = range(min($years), max($years));
    $data = [];
    foreach (array_reverse($range) as $year) {
      if (in_array($year, $years)) {
        $data = array_merge($data, array_filter($plan_data, function ($item) use ($year) {
          return $item['year'] == $year;
        }));
      }
      else {
        $data[] = [
          'year' => $year,
          'plan_type' => NULL,
          'plan_type_link' => NULL,
          'plan_type_tooltip' => NULL,
          'in_need' => NULL,
          'target' => NULL,
          'target_percent' => NULL,
          'reached' => NULL,
          'reached_percent' => NULL,
          'requirements' => NULL,
          'funding' => NULL,
          'coverage' => NULL,
          'footnotes' => NULL,
        ];
      }
    }
    return $data;
  }

  /**
   * Get the related plans for this element.
   *
   * @return \Drupal\ghi_plans\Entity\Plan[]
   *   An array of plan base objects.
   */
  private function getRelatedPlans() {
    $section = $this->getCurrentSectionNode();
    $plan_object = $section?->getBaseObject();
    if (!$plan_object instanceof Plan) {
      $plan_object = $this->getCurrentPlanObject();
    }
    return $plan_object ? $this->planManager->getRelatedPlans($plan_object) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationDefaults() {
    return [
      'columns' => [
        'in_need' => 'in_need',
        'target' => 'target',
        'target_percent' => 0,
        'reached' => 0,
        'reached_percent' => 0,
        'requirements' => 'requirements',
        'funding' => 'funding',
        'coverage' => 'coverage',
      ],
      'soft_limit' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Columns'),
      '#description' => $this->t('Select the columns to display in the table. If no column is checked, all will be displayed'),
      '#options' => [
        'in_need' => $this->t('People in need'),
        'target' => $this->t('People targeted'),
        'target_percent' => $this->t('People targeted (%)'),
        'reached' => $this->t('People reached'),
        'reached_percent' => $this->t('People reached (%)'),
        'requirements' => $this->t('Requirements ($)'),
        'funding' => $this->t('Funding ($)'),
        'coverage' => $this->t('% Funded'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'columns'),
    ];
    $form['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, 'soft_limit') ?? self::DEFAULT_MAX_YEARS, 3, 10);
    $form['soft_limit']['#description'] = $this->t('Choose how many lines will be shown initially. If there is more data, the table can be expanded by the user.') . ' ' . $form['soft_limit']['#description'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData();
  }

}
