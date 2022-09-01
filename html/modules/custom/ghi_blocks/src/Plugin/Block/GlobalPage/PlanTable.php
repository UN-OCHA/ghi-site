<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
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

  use GlobalSettingsTrait;
  use PlanFootnoteTrait;
  use TableSoftLimitTrait;
  use BlockCommentTrait;

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

    $build = [
      '#cache' => [
        'tags' => $table_data['cache_tags'],
      ],
    ];
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
    $conf = $this->getBlockConfig();
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

    $header = [
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
        'data' => $this->t('Expected reach'),
        'data-column-type' => 'amount',
      ],
      'reached' => [
        'data' => $this->t('Reached'),
        'data-column-type' => 'amount',
      ],
      'requirements' => [
        'data' => $this->t('Requirements'),
        'data-column-type' => 'amount',
      ],
      'funding' => [
        'data' => $this->t('Funding'),
        'data-column-type' => 'amount',
      ],
      'coverage' => [
        'data' => $this->t('Coverage'),
        'data-column-type' => 'percentage',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'data-column-type' => 'status',
        'sortable' => FALSE,
      ],
    ];
    if ($export) {
      $header['document'] = [
        'data' => $this->t('Document'),
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

      $footnotes = $plan_entity ? $this->getFootnotesForPlanBaseobject($plan_entity) : NULL;
      $document_uri = $plan_entity ? ($plan_entity->get('field_plan_document_link')->uri ?? NULL) : NULL;

      $rows[$plan->id()] = [
        'name' => [
          'data' => [
            [
              '#markup' => $plan->getName(),
            ],
          ],
          'data-value' => $plan->getName(),
        ],
        'type' => $plan->getTypeShortName(),
        'inneed' => [
          'data' => [
            [
              '#theme' => 'hpc_amount',
              '#amount' => $plan->getCaseloadValue('inNeed'),
            ],
            $this->buildFootnoteTooltip($footnotes, 'in_need'),
          ],
          'data-raw-value' => $plan->getCaseloadValue('inNeed'),
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        'targeted' => [
          'data' => [
            [
              '#theme' => 'hpc_amount',
              '#amount' => $plan->getCaseloadValue('target'),
            ],
            $this->buildFootnoteTooltip($footnotes, 'target'),
          ],
          'data-raw-value' => $plan->getCaseloadValue('target'),
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        'expected_reach' => [
          'data' => [
            [
              '#theme' => 'hpc_amount',
              '#amount' => $plan->getCaseloadValue('expectedReach', 'Expected Reach'),
            ],
            $this->buildFootnoteTooltip($footnotes, 'estimated_reach'),
          ],
          'data-raw-value' => $plan->getCaseloadValue('expectedReach', 'Expected Reach'),
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        'reached' => [
          'data' => [
            '#theme' => 'hpc_amount',
            '#amount' => $plan->getCaseloadValue('reached', 'Reached'),
          ],
          'data-raw-value' => $plan->getCaseloadValue('reached', 'Reached'),
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        'requirements' => [
          'data' => [
            [
              '#theme' => 'hpc_currency',
              '#value' => $plan->getRequirements($plan),
            ],
            $this->buildFootnoteTooltip($footnotes, 'requirements'),
          ],
          'data-raw-value' => $plan->getRequirements($plan),
          'data-column-type' => 'amount',
          'data-progress-group' => 'financial',
        ],
        'funding' => [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $plan->getFunding($plan),
          ],
          'data-raw-value' => $plan->getFunding($plan),
          'data-column-type' => 'amount',
          'data-progress-group' => 'financial',
        ],
        'coverage' => [
          'data' => [
            '#theme' => 'hpc_percent',
            '#ratio' => $plan->getCoverage($plan) / 100,
          ],
          'data-raw-value' => $plan->getCoverage($plan),
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
        ],
        'status' => [
          'data' => [
            '#type' => 'container',
            'content' => array_filter([
              'plan_status' => $plan_entity ? [
                '#theme' => 'plan_status',
                '#plan_entity' => $plan_entity,
              ] : NULL,
              'document' => $document_uri ? [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'data-toggle' => 'tooltip',
                  'data-tippy-content' => $this->t('Download the @type document', [
                    '@type' => strtolower($plan->getTypeShortName()) == 'other' ? $this->t('plan') : $plan->getTypeShortName(),
                  ]),
                ],
                'content' => DownloadHelper::getDownloadIcon($document_uri),
              ] : NULL,
            ]),
          ],
          'data-raw-value' => $plan_entity ? $plan_entity->getPlanStatusLabel() : '',
        ],
      ];
      if ($export) {
        $rows[$plan->id()]['document'] = [
          'data' => $document_uri,
        ];
      }
    }

    $this->applyTableConfiguration($header, $rows);
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
   * Apply the table configuration.
   *
   * @param array $header
   *   The build header array.
   * @param array $rows
   *   The build table rows.
   */
  private function applyTableConfiguration(array &$header, array &$rows) {
    $config = $this->getBlockConfig();
    $table_config = $config['table'] ?? [];

    if (empty($table_config['total_funding'])) {
      // Hide the funding column.
      unset($header['funding']);
      $rows = array_map(function ($row) {
        unset($row['funding']);
        return $row;
      }, $rows);
    }

    if (empty($table_config['funding_progress'])) {
      // Hide the coverage column.
      unset($header['coverage']);
      $rows = array_map(function ($row) {
        unset($row['coverage']);
        return $row;
      }, $rows);
    }

    if (empty($table_config['fts_icon'])) {
      $rows = array_map(function ($row) {

        return $row;
      }, $rows);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [
      'plans' => [
        'include' => 'hrp_status',
        'hrp_status' => 'hrp',
        'plan_types' => NULL,
        'hide_unpublished' => FALSE,
        'hide_empty_requirements' => FALSE,
      ],
      'table' => [
        'funding_progress' => TRUE,
        'total_funding' => FALSE,
        'fts_icon' => TRUE,
        'comment' => NULL,
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
    $form['plans']['include'] = [
      '#type' => 'radios',
      '#title' => $this->t('Include plans based on'),
      '#options' => [
        'plan_type' => $this->t('Plan type'),
        'hrp_status' => $this->t('HRP status'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'include',
      ]),
    ];
    $form['plans']['hrp_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Plan category'),
      '#options' => [
        'hrp' => $this->t('Plans with HRPs'),
        'nohrp' => $this->t('Plans without HRPs'),
        'rrp' => $this->t('Regional response plans'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'hrp_status',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="basic[plans][include]"]' => ['value' => 'hrp_status'],
        ],
      ],
    ];
    $form['plans']['plan_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Plan types'),
      '#options' => $this->getAvailablePlanTypes(TRUE),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'plan_types',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="basic[plans][include]"]' => ['value' => 'plan_type'],
        ],
      ],
    ];

    $form['plans']['hide_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide plans that have not been made publicly available in this site yet'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'hide_unpublished',
      ]),
      '#description' => $this->t('Check this if plans that have not been imported into HPC Viewer, or that have not been published, should be hidden from the table output.'),
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
    $form['table']['funding_progress'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show funding progress column'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'table',
        'funding_progress',
      ]),
      '#description' => $this->t('Check this to show the funding progress column.'),
    ];
    $form['table']['total_funding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show total funding column'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'table',
        'total_funding',
      ]),
      '#description' => $this->t('Check this to show the total funding column.'),
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

    // @codingStandardsIgnoreStart
    // $global_plan_options = hpc_entities_get_available_global_plan_options();
    // $include_global_plan_default = $this->getDefaultFormValueFromFormState($form_state, ['global_plan', 'global_plan']) ?: FALSE;
    // $form['global_plan'] = [
    //   '#type' => 'details',
    //   '#title' => $this->t('Global plan'),
    //   '#tree' => TRUE,
    //   '#group' => 'tabs',
    // ];
    // $form['global_plan']['include_global_plan_columns'] = [
    //   '#type' => 'checkbox',
    //   '#title' => $this->t('Include columns for requirements against a global plan'),
    //   '#default_value' => !empty($global_plan_options) && $include_global_plan_default,
    //   '#disabled' => empty($global_plan_options),
    //   '#description' => $this->t('Check this to add additional columns that show requirements for each plan against a global plan.'),
    // ];
    // if (empty($global_plan_options)) {
    //   $form['global_plan']['include_global_plan_columns']['#description'] .= ' ' . $this->t('<em>Disabled because no global plan has been found.</em>');
    // }
    // $form['global_plan']['global_plan_select'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Global plan'),
    //   '#options' => $global_plan_options,
    //   '#default_value' => array_key_exists('global_plan_columns', $conf) && !empty($conf['global_plan_columns']['global_plan_select']) ? $conf['global_plan_columns']['global_plan_select'] : '',
    //   '#description' => $this->t('Select the global plan.'),
    //   '#states' => [
    //     'visible' => [
    //       ':input[name="global_plan_columns[include_global_plan_columns]"]' => ['checked' => TRUE],
    //     ],
    //   ],
    // ];
    // $form['global_plan']['columns_select'] = [
    //   '#type' => 'checkboxes',
    //   '#title' => $this->t('Columns to be included'),
    //   '#options' => [
    //     'inside_global' => $this->t('Total requirements of each plan inside the global plan'),
    //     'cluster_total' => $this->t('Total requirements of each plan against the global plan for a specific global cluster'),
    //     'cluster_total_exclude' => $this->t('Total requirements of each plan against the global plan excluding a specific global cluster'),
    //     'outside_global' => $this->t('Total requirements of each plan outside the global plan'),
    //   ],
    //   '#default_value' => array_key_exists('global_plan_columns', $conf) && !empty($conf['global_plan_columns']['columns_select']) ? $conf['global_plan_columns']['columns_select'] : '',
    //   '#states' => [
    //     'visible' => [
    //       ':input[name="global_plan_columns[include_global_plan_columns]"]' => ['checked' => TRUE],
    //     ],
    //   ],
    // ];
    // $global_clusters = hpc_api_data_get_global_clusters();
    // $form['global_plan']['cluster_select'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Global cluster'),
    //   '#options' => array_map(function ($item) {
    //     return $item->name;
    //   }, $global_clusters),
    //   '#default_value' => array_key_exists('global_plan_columns', $conf) && !empty($conf['global_plan_columns']['cluster_select']) ? $conf['global_plan_columns']['cluster_select'] : '',
    //   '#description' => $this->t('Select the global cluster.'),
    //   '#states' => [
    //     'visible' => [
    //       [
    //         [
    //           ':input[name="global_plan_columns[include_global_plan_columns]"]' => ['checked' => TRUE],
    //           ':input[name="global_plan_columns[columns_select][cluster_total]"]' => ['checked' => TRUE],
    //         ],
    //         [
    //           ':input[name="global_plan_columns[include_global_plan_columns]"]' => ['checked' => TRUE],
    //           ':input[name="global_plan_columns[columns_select][cluster_total_exclude]"]' => ['checked' => TRUE],
    //         ],
    //       ],
    //     ],
    //   ],
    // ];
    // @codingStandardsIgnoreEnd

    return $form;
  }

  /**
   * Retrieve the plans to display in this block.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   Array of plan objects.
   */
  private function getPlans() {
    $plans = $this->getPlanQuery()->getPlans();
    if (empty($plans)) {
      return $plans;
    }
    $config = $this->getBlockConfig();
    $plans_config = $config['plans'] ?? [];

    if (empty($plans_config['include']) || $plans_config['include'] == 'hrp_status') {
      if (!empty($plans_config['hrp_status']) && $plans_config['hrp_status'] == 'rrp') {
        // Filter for regional response plans.
        $plans = array_filter($plans, function ($plan) {
          return $plan->isRrp();
        });
      }
      elseif (!empty($plans_config['hrp_status']) && $plans_config['hrp_status'] == 'nohrp') {
        // Filter for other plans.
        $plans = array_filter($plans, function ($plan) {
          return $plan->isOther();
        });
      }
      else {
        // Filter for HRPs and Flash appeals.
        $plans = array_filter($plans, function ($plan) {
          return $plan->isHrp() || $plan->isFlashAppeal();
        });
      }
    }
    elseif (!empty($plans_config['plan_types'])) {
      // Filter based on selected plan types.
      $selected_plan_type_tids = array_filter($plans_config['plan_types']);
      $plans = array_filter($plans, function ($plan) use ($selected_plan_type_tids) {
        $term = $this->getTermObjectByName($plan->getOriginalTypeName(), $plan->isTypeIncluded());
        return $term && in_array($term->id(), $selected_plan_type_tids);
      });
    }

    // Filter out plans without requirements.
    if (array_key_exists('hide_empty_requirements', $plans_config) && $plans_config['hide_empty_requirements']) {
      // Get information about published plans.
      foreach ($plans as $key => $plan) {
        if (empty($plan->getRequirements())) {
          unset($plans[$key]);
        }
      }
    }

    // Filter out plans without published sections.
    if (array_key_exists('hide_unpublished', $plans_config) && $plans_config['hide_unpublished']) {
      // Get information about published plans.
      foreach ($plans as $key => $plan) {
        $plan_base_object = $plan->getEntity();
        $section = $plan_base_object ? $this->sectionManager->loadSectionForBaseObject($plan_base_object) : NULL;
        if (!$section || !$section->isPublished()) {
          unset($plans[$key]);
        }
      }
    }

    // Apply the global configuration to limit the source data.
    $this->applyGlobalConfigurationPlans($plans, $this->getContextValue('year'));

    return $plans;
  }

  /**
   * Get the plan query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery
   *   The plan query plugin.
   */
  private function getPlanQuery() {
    return $this->getQueryHandler('plans');
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData(TRUE);
  }

}
