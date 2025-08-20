<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_blocks\Traits\TableTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Traits\RenderArrayTrait;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanCaseloadTrendsTable' block.
 *
 * @Block(
 *  id = "plan_caseload_trends_table",
 *  admin_label = @Translation("Caseload Trends Table"),
 *  category = @Translation("Plan elements"),
 *  default_title = @Translation("Evolution of the humanitarian response"),
 *  data_sources = {
 *    "attachment_search" = "attachment_search_query",
 *    "plan_funding" = "plan_funding_summary_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  }
 * )
 */
class PlanCaseloadTrendsTable extends GHIBlockBase implements OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface, TrustedCallbackInterface {

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
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planManager = $container->get('ghi_plans.manager');
    $instance->sectionManager = $container->get('ghi_sections.manager');

    // Write something to the session to make big pipe work also for anonymous
    // users on the first request for this block which will not have any cached
    // data.
    $request = $container->get('request_stack')->getCurrentRequest();
    $request->cookies->set($request->getSession()->getName(), 'big_pipe work around');

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
    if ($this->isPreview()) {
      // Just return the table if in preview mode.
      return $this->buildTable();
    }

    // Otherwise be a bit more sophisticated.
    $table = $this->buildTable($this->getBlockConfig()['soft_limit']);
    if (empty($table)) {
      return NULL;
    }

    // We return a lazy builder render array together with the actual
    // size-limited table to be used as a preview until the lazy-loader builds
    // the entire table. There is also logic in
    // themes/custom/common_design_subtheme/js/common.js to add the expand
    // button in a disabled state if the table will eventually be bigger than
    // what is defined as the soft limit.
    return [
      '#lazy_builder' => [
        static::class . '::lazyBuildTable',
        [
          $this->getPluginId(),
          $this->getUuid(),
          $this->getCurrentUri(),
        ],
      ],
      '#create_placeholder' => TRUE,
      '#cache' => [
        // Cache this per url and query to also capture the block settings.
        'context' => [
          'url.query_args',
          'url.path',
        ],
        'tags' => $this->getCacheTags(),
        'max' => $this->getCacheMaxAge(),
        // Adding these cache keys here will trigger autoplaceholdering.
        'keys' => [
          \Drupal::request()->getPathInfo(),
          \Drupal::request()->query->get('bs'),
        ],
      ],
      '#lazy_builder_preview' => $table,
    ];
  }

  /**
   * Lazy builder callback for attachment tables.
   *
   * @param string $plugin_id
   *   The plugin id of this block plugin.
   * @param string $block_uuid
   *   The uuid of this block plugins instance.
   * @param string $uri
   *   The current page uri.
   *
   * @return array
   *   A render array representing the tables.
   */
  public static function lazyBuildTable($plugin_id, $block_uuid, $uri) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable $block_instance */
    $block_instance = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block_instance) {
      return [];
    }
    $table = $block_instance->buildTable();

    // Reset the static caches to prevent memory issues. Lazy load callbacks
    // are part of the same main thread that renders the page. Given that there
    // can be an arbitrarily high number of these calls, especially on logframe
    // pages, we need to account for that by keeping memory under control. So
    // better to loose a bit of performance when it comes to lazy loading the
    // tables, than running into a memory issue and not showing some of the
    // tables at all.
    drupal_static_reset();

    return $table;
  }

  /**
   * Build the render array for the table.
   *
   * @param int|null $limit
   *   An optional limit for the number of table rows.
   *
   * @return array
   *   A render array for the table.
   */
  public function buildTable(?int $limit = NULL) {
    $table = $this->buildTableData($limit);
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
      '#soft_limit_show_disabled' => count($this->getRelatedPlans()) > $this->getBlockConfig()['soft_limit'],
      '#block_id' => $this->getBlockId(),
    ];
  }

  /**
   * Build the table data for this element.
   *
   * @param int|null $limit
   *   An optional limit for the table rows.
   *
   * @return array|null
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData(?int $limit = NULL) {
    $data = $this->buildSourceData($limit);
    if (empty($data)) {
      return NULL;
    }

    $plan_object = $this->getCurrentPlanObject();
    $langcode = $plan_object?->getPlanLanguage() ?? 'en';
    $t_options = ['langcode' => $langcode];

    $header = [
      'year' => $this->buildHeaderColumn($this->t('Year', [], $t_options), 'number'),
      'plan_type' => [
        'data' => $this->t('Type', [], $t_options),
        'class' => 'sorttable-alpha',
      ],
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
              '#tooltip' => $this->t('This plan is not included in the GHO totals', [], $t_options),
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
          if (!isset($row['data'])) {
            unset($row[$key]);
          }
          else {
            unset($row['data'][$key]);
          }
        }
      }
    }

    // Now fill in missing years.
    $years = array_unique(array_map(function ($item) {
      return $item['year'];
    }, $data));
    $range = range(min($years), max($years));
    $plan_rows = $rows;
    $rows = [];
    foreach (array_reverse($range) as $year) {
      if (in_array($year, $years)) {
        $rows = array_merge($rows, array_filter($plan_rows, function ($item) use ($year) {
          return $item['year']['data'] == $year;
        }));
      }
      else {
        $rows[] = [
          'data' => [
            'year' => [
              'data' => $year,
              'data-raw-value' => $year,
              'data-column-type' => 'string',
            ],
            'plan_type' => [
              'data' => [
                '#type' => 'html_tag',
                '#tag' => 'em',
                '#attributes' => [
                  'class' => ['no-plan'],
                ],
                '#value' => $this->t('There was no plan in this year.', [], $t_options),
              ],
              'data-raw-value' => (string) $this->t('There was no plan in this year.', [], $t_options),
              'data-sort-value' => -1,
              'colspan' => count(array_filter($columns)) + 1,
            ],
          ],
          'class' => 'empty no-plan',
        ];
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
   * @param int|null $limit
   *   An optional limit for the number of plans to retrieve.
   *
   * @return array|null
   *   An array with data or NULL.
   */
  private function buildSourceData(?int $limit = NULL) {
    $related_plans = $this->getRelatedPlans();
    if (empty($related_plans)) {
      return NULL;
    }

    if ($limit !== NULL) {
      $related_plans = array_slice($related_plans, 0, $limit, TRUE);
    }

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $attachments_query */
    $attachments_query = $this->getQueryHandler('attachment_search');

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery $funding_query */
    $funding_query = $this->getQueryHandler('plan_funding');

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
      $funding_data = $funding_query->getData(['plan_id' => $plan->getSourceId()]);

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
    return $plan_data;
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
    $related_plans = $plan_object ? $this->planManager->getRelatedPlans($plan_object) : [];

    // Filter out plans without a plan type.
    $related_plans = array_filter($related_plans, function (Plan $plan) {
      return $plan->getPlanType() !== NULL;
    });
    // Filter out restricted plans.
    $related_plans = array_filter($related_plans, function (Plan $plan) {
      return !$plan->isRestricted();
    });
    // Initially sort by descending year.
    uasort($related_plans, function ($a, $b) {
      return strnatcasecmp($b->getYear(), $a->getYear());
    });
    return $related_plans;
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

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'lazyBuildTable',
    ];
  }

}
