<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_blocks\Traits\GlobalPlanOverviewBlockTrait;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_plans\ApiObjects\Mocks\PlanOverviewPlanMock;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\FieldHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_downloads\Helpers\DownloadHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanTable' block.
 *
 * @Block(
 *  id = "global_plan_table",
 *  admin_label = @Translation("Plan table"),
 *  category = @Translation("Global"),
 *  data_sources = {
 *    "plans" = "plan_overview_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class PlanTable extends GHIBlockBase implements HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use GlobalPlanOverviewBlockTrait;
  use GlobalSettingsTrait;
  use PlanFootnoteTrait;
  use TableSoftLimitTrait;
  use BlockCommentTrait;
  use FtsLinkTrait;

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
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table_data = $this->buildTableData();
    if ($table_data === NULL) {
      return NULL;
    }
    $conf = $this->getBlockConfig();

    $build = [
      '#cache' => [
        'tags' => $table_data['cache_tags'],
      ],
      '#wrapper_attributes' => [
        'class' => ['content-width'],
      ],
    ];
    if (!empty($conf['table']['top_note'])) {
      $build[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['metadata']],
        0 => [
          '#markup' => new FormattableMarkup($conf['table']['top_note'], [
            '@date' => date('d F Y'),
          ]),
        ],
      ];
    }
    $build[] = [
      '#theme' => 'table',
      '#header' => $table_data['header'],
      '#rows' => $table_data['rows'],
      '#sortable' => TRUE,
      '#searchable' => TRUE,
      '#autosort' => FALSE,
      '#wrapper_attributes' => [
        'class' => ['plan-table'],
      ],
      '#progress_groups' => TRUE,
      '#soft_limit' => $this->getBlockConfig()['table']['soft_limit'] ?? 0,
    ];

    $comment = $this->buildBlockCommentRenderArray($conf['table']['comment'] ?? NULL);
    if ($comment) {
      $comment['#attributes']['class'][] = 'content-width';
      $build['comment'] = $comment;
    }
    return $build;
  }

  /**
   * Build a table representation of the plan data.
   *
   * @param bool $export
   *   Whether the table should be build for export or for display.
   *
   * @return array
   *   A render array for a table.
   */
  public function buildTableData($export = FALSE) {
    $plans = $this->getPlans();
    if (empty($plans)) {
      return NULL;
    }
    $year = $this->getContextValue('year');

    $header = [];
    if ($export) {
      $header['plan_id'] = $this->t('Plan ID');
    }

    $header += [
      'name' => $this->t('Plans'),
      'type' => $this->t('Plan type'),
      'inneed' => [
        'data' => $this->t('People in need'),
        'data-column-type' => 'amount',
      ],
      'targeted' => [
        'data' => $this->t('People targeted'),
        'data-column-type' => 'amount',
      ],
      'expected_reach' => [
        'data' => $this->t('Estimated Reach'),
        'data-column-type' => 'amount',
      ],
      'latest_reach' => [
        'data' => $this->t('People reached'),
        'data-column-type' => 'percentage',
      ],
      'reached' => [
        'data' => $this->t('% Reached'),
        'data-column-type' => 'percentage',
      ],
      'requirements' => [
        'data' => $this->t('Requirements'),
        'data-column-type' => 'currency',
      ],
      'funding' => [
        'data' => $this->t('Funding'),
        'data-column-type' => 'currency',
      ],
      'coverage' => [
        'data' => $this->t('% Funded'),
        'data-column-type' => 'percentage',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'data-column-type' => 'status',
        'sortable' => FALSE,
      ],
    ];
    if ($export) {
      $header['in_gho'] = $this->t('In GHO');
      $header['document'] = [
        'data' => $this->t('Document'),
        'data-column-type' => 'document',
      ];
      $header['link_ha'] = [
        'data' => $this->t('Link to HA page'),
        'data-column-type' => 'document',
      ];
      $header['link_fts'] = [
        'data' => $this->t('Link to FTS page'),
        'data-column-type' => 'document',
      ];
    }

    $cache_tags = [];
    $rows = [];

    foreach ($plans as $plan) {
      $plan_entity = $plan->getEntity();
      if ($plan_entity) {
        $cache_tags = Cache::mergeTags($cache_tags, $plan_entity->getCacheTags());
      }

      // Setup the PiN values.
      $in_need = $plan->getCaseloadValue('inNeed');
      $target = $plan->getCaseloadValue('target');
      $latest_reached = $plan->getCaseloadValue('latestReach');

      $reached_percent = !empty($latest_reached) && !empty($target) ? 100 / $target * $latest_reached : NULL;
      if ($plan instanceof PlanOverviewPlanMock) {
        $reached_percent = ((float) $plan->getCaseloadValue('reached_percent')) * 100;
      }
      $expected_reached = $plan->getCaseloadValue('expectedReach', 'Expected Reach');

      // Setup the financial values.
      $requirements = $plan->getRequirements($plan);
      $funding = $plan->getFunding($plan);
      $coverage = $plan->getCoverage($plan);

      // Setup number formatting.
      $decimals = 1;

      // Setup footnotes and document links.
      $footnotes = $plan_entity ? $this->getFootnotesForPlanBaseobject($plan_entity) : NULL;
      if ($footnotes === NULL && $plan instanceof PlanOverviewPlanMock) {
        $footnotes = (object) [
          'requirements' => $plan->getRequirementsFootnote(),
        ];
      }

      $link_to_fts = $plan_entity ? $plan_entity->canLinkToFts() : FALSE;
      $document_uri = $plan->getPlanDocumentUri();

      // Setup the column values.
      $value_in_need = $in_need ? [
        '#theme' => 'hpc_amount',
        '#amount' => $in_need,
        '#decimals' => $decimals,
      ] : [
        '#markup' => '-',
      ];
      $value_targeted = $target ? [
        '#theme' => 'hpc_amount',
        '#amount' => $target,
        '#decimals' => $decimals,
      ] : [
        '#markup' => '-',
      ];
      $value_expected_reach = [
        '#theme' => 'hpc_amount',
        '#amount' => $expected_reached,
        '#decimals' => $decimals,
      ];
      $value_latest_reached = $latest_reached !== NULL ? [
        '#theme' => 'hpc_amount',
        '#amount' => $latest_reached,
        '#decimals' => $decimals,
      ] : [
        '#markup' => $this->t('Pending'),
      ];
      $value_reached = $reached_percent ? [
        '#theme' => 'hpc_percent',
        '#ratio' => $reached_percent / 100,
      ] : [
        '#markup' => $this->t('Pending'),
      ];
      $value_requirements = [
        '#theme' => 'hpc_currency',
        '#value' => $requirements,
      ];
      $value_funding = [
        '#theme' => 'hpc_currency',
        '#value' => $funding,
      ];
      $value_coverage = [
        '#theme' => 'hpc_percent',
        '#ratio' => $coverage / 100,
      ];

      $rows[$plan->id()] = [];

      if ($export) {
        $rows[$plan->id()]['plan_id'] = ['data' => $plan->id()];
      }

      $rows[$plan->id()] += [
        'name' => [
          'data' => [
            'name' => [
              '#markup' => $plan->getName(),
            ],
          ],
          'data-value' => $plan->getName(),
          'data-raw-value' => $plan->getName(),
        ],
        'type' => [
          'data' => [
            'name' => [
              '#markup' => $plan->getTypeShortName(TRUE),
            ],
          ],
          'data-value' => $plan->getTypeShortName(TRUE),
        ],
        'inneed' => [
          'data' => [
            $value_in_need,
            $this->buildFootnoteTooltip($footnotes, 'in_need'),
          ],
          'data-raw-value' => $in_need,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_commentary' => $this->getFootnoteForProperty($footnotes, 'in_need'),
        ],
        'targeted' => [
          'data' => [
            $value_targeted,
            $this->buildFootnoteTooltip($footnotes, 'target'),
          ],
          'data-raw-value' => $target,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_commentary' => $this->getFootnoteForProperty($footnotes, 'target'),
        ],
        'expected_reach' => [
          'data' => [
            $value_expected_reach,
            $this->buildFootnoteTooltip($footnotes, 'estimated_reach'),
          ],
          'data-raw-value' => $expected_reached,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
          'export_commentary' => $this->getFootnoteForProperty($footnotes, 'estimated_reach'),
        ],
        'latest_reach' => [
          'data' => $value_latest_reached,
          'data-raw-value' => $latest_reached,
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        'reached' => [
          'data' => $value_reached,
          'data-raw-value' => $reached_percent,
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
        ],
        'requirements' => [
          'data' => [
            $link_to_fts ? self::buildFtsLink($value_requirements, $plan_entity, 'summary') : $value_requirements,
            $this->buildFootnoteTooltip($footnotes, 'requirements'),
          ],
          'data-raw-value' => $requirements,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
          'export_commentary' => $this->getFootnoteForProperty($footnotes, 'requirements'),
        ],
        'funding' => [
          'data' => [
            $link_to_fts ? self::buildFtsLink($value_funding, $plan_entity, 'summary') : $value_funding,
            $this->buildFootnoteTooltip($footnotes, 'funding'),
          ],
          'data-raw-value' => $funding,
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
        ],
        'coverage' => [
          'data' => $link_to_fts ? self::buildFtsLink($value_coverage, $plan_entity, 'summary') : $value_coverage,
          'data-raw-value' => $coverage,
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
        ],
        'status' => [
          'data' => [
            '#type' => 'container',
            'content' => array_filter([
              'plan_status' => [
                '#theme' => 'plan_status',
                '#status' => strtolower($plan->getPlanStatus() ? 'published' : 'unpublished'),
                '#status_label' => $plan->getPlanStatusLabel(),
              ],
              'document' => $document_uri ? [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'data-toggle' => 'tooltip',
                  'data-tippy-content' => $this->t('Download the document'),
                ],
                'content' => DownloadHelper::getDownloadIcon($document_uri),
              ] : NULL,
            ]),
          ],
          'data-raw-value' => $plan_entity ? $plan_entity->getPlanStatusLabel() : '',
        ],
      ];
      if ($export) {
        $rows[$plan->id()]['in_gho'] = $plan->isPartOfGho() ? $this->t('Yes') : $this->t('No');
        $rows[$plan->id()]['document'] = [
          'data' => $document_uri,
        ];
        $section = $plan_entity ? $this->sectionManager->loadSectionForBaseObject($plan_entity) : NULL;
        $rows[$plan->id()]['link_ha'] = [
          'data' => $section ? 'https://humanitarianaction.info' . $section->toUrl()->toString() : NULL,
        ];
        $rows[$plan->id()]['link_fts'] = [
          'data' => $plan_entity?->toUrl('fts_summary'),
        ];
      }
    }

    $this->applyTableConfiguration($header, $rows, $plans);
    $this->applyGlobalConfigurationTable($header, $rows, $cache_tags, $year, $plans);

    if (empty($rows)) {
      return NULL;
    }

    return [
      'header' => $header,
      'rows' => $rows,
      'cache_tags' => $cache_tags,
    ];
  }

  /**
   * Get the custom plan rows if configured.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Mocks\PlanOverviewPlanMock
   *   An array of mocked plan overview response objects.
   */
  private function getCustomPlanRows() {
    $conf = $this->getBlockConfig();
    $custom_rows = $conf['custom_rows']['rows'] ?? [];
    $custom_rows = array_filter($custom_rows, function ($row) {
      return !empty($row['plan_name']);
    });
    if (!empty($custom_rows)) {
      foreach ($custom_rows as $key => $custom_row) {
        $custom_rows[$key] = new PlanOverviewPlanMock((object) $custom_row);
      }
    }
    return $custom_rows;
  }

  /**
   * Apply the table configuration.
   *
   * @param array $header
   *   The build header array.
   * @param array $rows
   *   The build table rows.
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   An array of plan objects.
   */
  private function applyTableConfiguration(array &$header, array &$rows, array $plans) {
    $config = $this->getBlockConfig();
    $table_config = $config['table'] ?? [];
    if (empty($table_config['fts_icon'])) {
      $rows = array_map(function ($row) {
        // @todo Something should happen here.
        return $row;
      }, $rows);
    }

    // Support links on custom rows (using a mock object).
    $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
      /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
      $plan = $plans[$plan_id] ?? NULL;
      if (!$plan || !$plan instanceof PlanOverviewPlanMock) {
        // We only want to setup links for custom rows.
        return $row;
      }
      $plan_link = $plan->toLink();
      if (!$plan_link) {
        return $row;
      }
      $row['name'] = ['data' => $plan_link->toRenderable()];
      return $row;
    }, $rows);
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [
      'plans' => [
        'hide_unpublished' => FALSE,
        'hide_empty_requirements' => FALSE,
      ],
      'table' => [
        'top_note' => NULL,
        'fts_icon' => TRUE,
        'comment' => NULL,
      ],
      'custom_rows' => [
        'replace' => FALSE,
        'ignore_filters' => TRUE,
        'rows' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    $form['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['plans'] = [
      '#type' => 'details',
      '#title' => $this->t('Plans'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];

    $form['plans']['hide_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide plans that have not been made publicly available in this site yet'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'hide_unpublished',
      ]),
      '#description' => $this->t('Check this if plans that have not been imported yet, or that have not been published, should be hidden from the table output.'),
    ];
    $form['plans']['hide_empty_requirements'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide plans that have no requirements yet'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'hide_empty_requirements',
      ]),
      '#description' => $this->t('Check this if plans that have no requirements yet should be hidden from the table output.'),
    ];
    $form['table'] = [
      '#type' => 'details',
      '#title' => $this->t('Table'),
      '#description' => $this->t('The following settings allow you to toggle some features for <em>this single table instance</em>. More <em>global settings</em>, that apply to various page elements across a year, can be controlled on the <a href="@url" target="_blank">GHI Global settings page</a>.', [
        '@url' => Url::fromRoute('ghi_blocks.global_config', [], ['query' => ['year' => $this->getContextValue('year')]])->toString(),
      ]),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $form['table']['top_note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top note'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'table',
        'top_note',
      ]),
      '#description' => $this->t('You can enter a short text to show on the top right of the table and can include a dynamic date using this form: <em>"Live data updated on @date"</em>. Leave empty to not show any text.'),
    ];
    $form['table']['fts_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include tooltip icons for FTS'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'table',
        'fts_icon',
      ]),
      '#description' => $this->t('Check this to show icons for "Tracked on FTS".'),
      '#disabled' => TRUE,
    ];
    $form['table']['comment'] = $this->buildBlockCommentFormElement($this->getDefaultFormValueFromFormState($form_state, [
      'table',
      'comment',
    ]));
    $form['table']['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, [
      'table',
      'soft_limit',
    ]));

    $tooltip_full_amount = ThemeHelper::render([
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $this->t('Enter full integers without any number formatting, e.g. 1000000.'),
    ], FALSE);
    $tooltip_full_decimal = ThemeHelper::render([
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $this->t('Enter decimals between 0 and 1, using a point as the decimal separator, e.g. 0.4.'),
    ], FALSE);
    $plan_types = $this->getAvailablePlanTypes(TRUE);
    $plan_status_options = FieldHelper::getBooleanFieldOptions('base_object', 'plan', 'field_released');

    $form['custom_rows'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom rows'),
      '#description' => $this->t('You can add custom rows that will be displayed as part of the plan table.'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $form['custom_rows']['replace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace API plans'),
      '#description' => $this->t('Check this to discard all plans coming from the API and to use only the rows configured here. Note that this applies only if at least a single row is added below.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'custom_rows',
        'replace',
      ]),
    ];
    $form['custom_rows']['ignore_filters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore standard filters'),
      '#description' => $this->t('Check this to ignore the configured filters (e.g. by plan type or to hide rows with empty requirements.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'custom_rows',
        'ignore_filters',
      ]),
    ];

    $form['custom_rows']['rows'] = [
      '#type' => 'custom_table_rows',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'custom_rows',
        'rows',
      ]),
      '#columns' => [
        'plan_name' => $this->t('Plan name'),
        'link' => [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Link'),
          '#target_type' => 'node',
          '#tags' => TRUE,
          '#selection_handler' => 'views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'content_autocomplete',
              'display_name' => 'entity_reference',
              'arguments' => ['article+section'],
            ],
            'match_operator' => 'CONTAINS',
          ],
        ],
        'plan_type' => [
          '#type' => 'select',
          '#title' => $this->t('Plan type'),
          '#options' => $plan_types,
        ],
        'plan_status' => [
          '#type' => 'select',
          '#title' => $this->t('Plan status'),
          '#options' => $plan_status_options,
        ],
        'people_in_need' => $this->t('In Need'),
        'people_target' => $this->t('Targeted'),
        'estimated_reached' => $this->t('Estimated Reach'),
        'people_latest_reached' => $this->t('Latest Reached'),
        'people_reached_percent' => $this->t('% Reached'),
        'total_funding' => $this->t('Funding'),
        'total_requirements' => $this->t('Required'),
        'funding_progress' => $this->t('% Funded'),
        'in_gho' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Part of GHO'),
        ],
        'required_footnote' => [
          '#type' => 'textarea',
          '#title' => $this->t('Required footnote'),
        ],
      ],
      '#column_tooltips' => [
        'people_in_need' => $tooltip_full_amount,
        'people_target' => $tooltip_full_amount,
        'estimated_reached' => $tooltip_full_amount,
        'people_latest_reached' => $tooltip_full_amount,
        'people_reached_percent' => $tooltip_full_decimal,
        'total_funding' => $tooltip_full_amount,
        'total_requirements' => $tooltip_full_amount,
        'funding_progress' => $tooltip_full_decimal,
      ],
    ];
    return $form;
  }

  /**
   * Retrieve the plans to display in this block.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   Array of plan objects.
   */
  private function getPlans() {
    $config = $this->getBlockConfig();
    $defaults = $this->getConfigurationDefaults();
    $custom_rows_config = $config['custom_rows'] ?? $defaults['custom_rows'];
    $plans_config = $config['plans'] ?? $defaults['plans'];

    $plans = $this->getPlanQuery()->getPlans();
    $custom_rows = $this->getCustomPlanRows();
    if (!empty($custom_rows)) {
      if ($custom_rows_config['replace']) {
        $plans = $custom_rows;
      }
      else {
        $plans = array_merge($plans, $custom_rows);
      }
    }
    if (empty($plans)) {
      return $plans;
    }

    $plans_config = $config['plans'] ?? [];

    // Filter out plans without requirements.
    if (array_key_exists('hide_empty_requirements', $plans_config) && $plans_config['hide_empty_requirements']) {
      $plans = array_filter($plans, function ($plan) use ($custom_rows_config) {
        if ($custom_rows_config['ignore_filters'] && $plan instanceof PlanOverviewPlanMock) {
          return TRUE;
        }
        return !empty($plan->getRequirements());
      });
    }

    // Filter out plans without published sections.
    if (array_key_exists('hide_unpublished', $plans_config) && $plans_config['hide_unpublished']) {
      // Get information about published plans.
      $plans = array_filter($plans, function ($plan) use ($custom_rows_config) {
        if ($custom_rows_config['ignore_filters'] && $plan instanceof PlanOverviewPlanMock) {
          return TRUE;
        }
        $plan_base_object = $plan->getEntity();
        $section = $plan_base_object ? $this->sectionManager->loadSectionForBaseObject($plan_base_object) : NULL;
        return $section && $section->isPublished();
      });
    }

    // Apply the global configuration to limit the source data.
    $this->applyGlobalConfigurationPlans($plans, $this->getContextValue('year'));

    return $plans;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData(TRUE);
  }

}
