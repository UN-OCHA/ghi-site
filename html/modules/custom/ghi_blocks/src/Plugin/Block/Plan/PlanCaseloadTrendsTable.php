<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_blocks\Traits\TableTrait;
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
 *    "plan_funding" = "plan_funding_summary_query"
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

    $source_data = $this->buildSourceData();
    $years = array_map(function ($item) {
      return $item['year'];
    }, $source_data);
    $soft_limit = $this->getBlockConfig()['soft_limit'];
    $index = $soft_limit ? array_search((int) date('Y') - (int) $soft_limit, $years) : NULL;
    return [
      '#theme' => 'table',
      '#header' => $table['header'],
      '#rows' => $table['rows'],
      '#progress_groups' => TRUE,
      '#sortable' => TRUE,
      '#soft_limit' => $index,
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
      $this->buildHeaderColumn($this->t('Year', [], $t_options), 'number'),
      $this->t('Type'),
      $this->buildHeaderColumn($this->t('People in need', [], $t_options), 'amount'),
      $this->buildHeaderColumn($this->t('People targeted', [], $t_options), 'amount'),
      $this->buildHeaderColumn($this->t('Requirements ($)', [], $t_options), 'currency'),
      $this->buildHeaderColumn($this->t('Funding ($)', [], $t_options), 'currency'),
      $this->buildHeaderColumn($this->t('% Funded', [], $t_options), 'percentage'),
    ];
    $rows = [];

    $theme_options = [
      'decimals' => 1,
      'decimal_format' => $plan_object?->getDecimalFormat(),
    ];

    foreach ($data as $item) {
      $row = [
        [
          'data' => $item['year'],
          'data-raw-value' => $item['year'],
          'data-column-type' => 'string',
        ],
        [
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
        [
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
        [
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
        [
          'data' => [
            $this->buildRenderArray('hpc_currency', $item['current_requirements'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'requirements'),
          ],
          'data-raw-value' => $item['current_requirements'] ?? 0,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
          'export_value' => $item['current_requirements'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'requirements'),
        ],
        [
          'data' => [
            $this->buildRenderArray('hpc_currency', $item['total_funding'] ?? FALSE, $theme_options),
            $this->buildFootnoteTooltip($item['footnotes'], 'funding'),
          ],
          'data-raw-value' => $item['total_funding'] ?? 0,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
          'export_value' => $item['total_funding'],
          'export_commentary' => $this->getFootnoteForProperty($item['footnotes'], 'funding'),
        ],
        [
          'data' => $this->buildRenderArray('hpc_percent', $item['funding_coverage'] ?? FALSE, $theme_options),
          'data-raw-value' => $item['funding_coverage'] ?? 0,
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
          'export_value' => $item['funding_coverage'],
        ],
      ];
      $rows[] = $row;
    }

    if (empty($rows)) {
      return NULL;
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
    $related_sections = $this->getRelatedSections();
    if (empty($related_sections)) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $attachments_query */
    $attachments_query = $this->getQueryHandler('attachment_search');

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery $funding_query */
    $funding_query = $this->getQueryHandler('plan_funding');

    $plan_data = [];
    $plan_types = [];
    foreach ($related_sections as $section) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $plan = $section->getBaseObject();
      $plan_year = $plan->getYear();
      $plan_type = $plan->getPlanTypeShortLabel(FALSE);
      $plan_types[$plan_year][$plan_type] = !empty($plan_types[$plan_year][$plan_type]) ? $plan_types[$plan_year][$plan_type] + 1 : 1;
    }

    foreach ($related_sections as $section) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $plan = $section->getBaseObject();
      $plan_year = $plan->getYear();
      $plan_type = $plan->getPlanTypeShortLabel(FALSE);

      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment[] $caseloads */
      $caseloads = $attachments_query->getAttachmentsByObject('plan', $plan->getSourceId(), ['type' => 'caseload']);
      $caseload = count($caseloads) > 1 ? $plan->getPlanCaseload($caseloads) : (!empty($caseloads) ? reset($caseloads) : NULL);
      $funding_data = $funding_query->getData(['plan_id' => $plan->getSourceId()]);

      // Setup the plan type label to distinguish years with multiple plans of
      // the same type.
      $plan_type_label = $plan_types[$plan_year][$plan_type] > 1 ? $plan_type . ' - ' . $plan->getShortName() : $plan_type;

      $plan_data[] = [
        'year' => $plan_year,
        'plan_type' => $plan_type_label,
        'plan_type_link' => $section->access('view') ? $section->toLink($plan_type_label)->toRenderable() : ['#markup' => $plan_type_label],
        'plan_type_tooltip' => !$plan->isPartOfGho(),
        'in_need' => $caseload?->getFieldByType('inNeed')?->value,
        'target' => $caseload?->getFieldByType('target')?->value,
        'current_requirements' => $funding_data['current_requirements'],
        'total_funding' => $funding_data['total_funding'],
        'funding_coverage' => $funding_data['funding_coverage'],
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
          'current_requirements' => NULL,
          'total_funding' => NULL,
          'funding_coverage' => NULL,
          'footnotes' => NULL,
        ];
      }
    }
    return $data;
  }

  /**
   * Get the related sections for this element.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes associated to plan base objects.
   */
  private function getRelatedSections() {
    $section = $this->getCurrentSectionNode();
    return $section ? $this->sectionManager->getRelatedSections($section) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationDefaults() {
    return [
      'soft_limit' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, [
      'soft_limit',
    ]) ?? self::DEFAULT_MAX_YEARS, 3, 10);
    $form['soft_limit']['#description'] = $this->t('Choose how many years will be shown initially. If there is data for more years, the table can be expanded by the user.') . ' ' . $form['soft_limit']['#description'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData();
  }

}
