<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for disaggregation modals.
 */
class DisaggregationModalController extends ControllerBase {

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * Public constructor.
   */
  public function __construct(EndpointQueryManager $endpoint_query_manager) {
    $this->iconQuery = $endpoint_query_manager->createInstance('icon_query');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.endpoint_query_manager'),
    );
  }

  /**
   * Get the title for the modal.
   */
  private function modalTitle(Attachment $attachment, MetricType $metric_type, $reporting_period_id) {
    $field = $attachment->getFieldByType($metric_type->getMachineName());
    $metric_type_name = $metric_type->getMachineName();
    $entity = $attachment->getSourceEntity();
    $icon_embed = $entity instanceof GoverningEntity && $entity->hasIcon() ? $this->iconQuery->getIconEmbedCode($entity->getIcon()) : NULL;

    $formatted_period = NULL;
    if ($metric_type_name && $attachment->isMeasurementField($metric_type_name) && $reporting_period = $attachment->getReportingPeriod($reporting_period_id)) {
      $formatted_period = new FormattableMarkup('<span class="title-additional-info">@formatted_period</span>', [
        '@formatted_period' => match ($attachment->isCumulativeReachField($metric_type_name)) {
          TRUE => $reporting_period->format('Monitoring period: @data_range_cumulative'),
          FALSE => $reporting_period->format('Monitoring period @period_number: @date_range'),
        },
      ]);
    }

    return Markup::create($icon_embed . $entity->getName() . ' | ' . $field . $formatted_period);
  }

  /**
   * Load content for a disaggregation modal window.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment object.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to show.
   * @param int $reporting_period
   *   The reporting period id for which to retrieve the data.
   *
   * @return array
   *   A render array.
   */
  public function loadDisaggregationModalData(Attachment $attachment, MetricType $metric_type, $reporting_period) {
    $cid = implode('-', [
      __FUNCTION__,
      $attachment->id(),
      $metric_type->id(),
      $reporting_period,
    ]);
    $cache = $this->cache();
    $cached_build = $cache->get($cid);
    if ($cached_build) {
      $build = $cached_build->data;
    }
    else {
      $build = $this->buildDisaggregationModalContent($attachment, $metric_type, $reporting_period);
      $cache->set($cid, $build);
    }
    return [
      '#type' => 'container',
      '#attached' => [
        'library' => ['ghi_blocks/modal'],
        'drupalSettings' => [
          'ghi_modal_title' => $this->modalTitle($attachment, $metric_type, $reporting_period),
        ],
      ],
      'content' => $build,
    ];
  }

  /**
   * Build content for a disaggregation modal window.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment object.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to show.
   * @param int $reporting_period
   *   The reporting period id for which to retrieve the data.
   *
   * @return array
   *   A render array.
   */
  private function buildDisaggregationModalContent(Attachment $attachment, MetricType $metric_type, $reporting_period) {

    $unit_type = $attachment->getUnitType();
    $unit_defaults = [
      'amount' => [
        '#scale' => 'full',
      ],
    ];

    $decimal_format = NULL;
    $plan_object = $attachment->getPlanObject();
    $decimal_format = $plan_object?->getDecimalFormat();
    $plan_language = $plan_object?->getPlanLanguage();

    $t_options = ['langcode' => $plan_language];

    // Retrieve disaggregated data form the attachment. The results is an
    // object with the properties 'locations', 'categories' and 'metrics'.
    // The latter two are for lookups, the actual data is contained in the
    // items under 'locations'.
    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period, $metric_type);
    if (empty($disaggregated_data->metrics[$metric_type->id()])) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('We did not find the requested information. If you think that this an error, please get in touch.', [], $t_options),
      ];
    }

    if (empty($disaggregated_data->locations)) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('We could not find suitable information to display here.', [], $t_options),
      ];
    }

    // Get the categories.
    $categories = $disaggregated_data->categories;

    // Build the table.
    $header = [
      [
        'data' => $this->t('Location', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-sort-order' => 'ASC',
        'data-column-type' => 'string',
      ],
    ];
    if (!empty($categories)) {
      foreach ($categories as $category) {
        $header[] = [
          'data' => $category,
          'data-sort-type' => 'numeric',
          'data-column-type' => $unit_type,
          'data-formatting' => 'numeric-full',
        ];
      }
    }
    else {
      $header[] = [
        'data' => $this->t('Totals', [], $t_options),
        'data-sort-type' => 'numeric',
        'data-column-type' => $unit_type,
        'data-formatting' => 'numeric-full',
      ];
    }

    // Go over the data and create the table rows.
    $rows = [];
    $totals = [];

    // Inital sorting by name.
    usort($disaggregated_data->locations, fn ($loc_1, $loc_2) => strnatcasecmp($loc_1->location['name'], $loc_2->location['name']));

    // Get a shortcut to the actual location data.
    $locations = array_map(fn ($location) => $location->location, $disaggregated_data->locations);
    $location_ids = array_map(fn ($location) => $location['id'], $locations);
    $locations = array_combine($location_ids, $locations);

    foreach ($disaggregated_data->locations as $location) {
      $row = [];
      $parents = array_key_exists('id', $location->location) ? $this->getLocationParents($locations, $location->location['id']) : NULL;
      if (!$parents || !is_array($parents)) {
        $row[] = $location->location['name'];
      }
      else {
        $parents[] = $location->location['name'];
        $row[] = implode(' > ', $parents);
      }

      if (!empty($categories)) {
        foreach (array_keys($categories) as $key) {
          $category_value = $location->categories[$key][$metric_type->id()] ?? NULL;
          $totals[$key] = (int) ($totals[$key] ?? 0) + (int) ($category_value ?? 0);
          $row[] = [
            'data' => [
              '#theme' => 'hpc_autoformat_value',
              '#value' => $category_value,
              '#unit_type' => $unit_type,
              '#unit_defaults' => $unit_defaults,
              '#decimal_format' => $decimal_format,
            ],
            'data-sort-value' => $category_value,
            'data-sort-type' => 'numeric',
            'data-column-type' => $unit_type,
            'data-formatting' => 'numeric-full',
          ];
        }
      }

      // Add the location total to the row as the last item if there are no
      // categories.
      if (empty($categories)) {
        $row[] = [
          'data' => [
            '#theme' => 'hpc_autoformat_value',
            '#value' => $location->totals[$metric_type->id()],
            '#unit_type' => $unit_type,
            '#unit_defaults' => $unit_defaults,
            '#decimal_format' => $decimal_format,
          ],
          'data-sort-value' => $location->totals[$metric_type->id()],
          'data-sort-type' => 'numeric',
          'data-column-type' => $unit_type,
          'data-formatting' => 'numeric-full',
        ];
        $totals[0] = ($totals[0] ?? 0) + (int) $location->totals[$metric_type->id()];
      }

      $rows[] = [
        'data' => $row,
        'data-location-id' => $location->location['id'],
      ];
    }

    // Initial sorting by the first column, which contains the (composed) name.
    usort($rows, fn ($a, $b) => strnatcasecmp($a['data'][0], $b['data'][0]));

    $total_row = [
      'data' => [
        $this->t('Total', [], $t_options),
      ],
      'class' => 'totals-row',
    ];
    if (!empty($categories)) {
      foreach (array_keys($categories) as $key) {
        $total_row['data'][] = [
          'data' => [
            '#theme' => 'hpc_autoformat_value',
            '#value' => $totals[$key],
            '#unit_type' => $unit_type,
            '#unit_defaults' => $unit_defaults,
            '#decimal_format' => $decimal_format,
          ],
          'data-sort-type' => 'numeric',
          'data-column-type' => $unit_type,
          'data-formatting' => 'numeric-full',
        ];
      }
    }
    else {
      $total_row['data'][] = [
        'data' => [
          '#theme' => 'hpc_autoformat_value',
          '#value' => $totals[0],
          '#unit_type' => $unit_type,
          '#unit_defaults' => $unit_defaults,
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-type' => 'numeric',
        'data-column-type' => $unit_type,
        'data-formatting' => 'numeric-full',
      ];
    }

    return [
      '#theme' => 'table',
      '#attributes' => [
        'class' => ['disaggregation-table'],
      ],
      '#cell_wrapping' => FALSE,
      '#header' => $header,
      '#rows' => $rows,
      '#sticky_rows' => [$total_row],
      '#sticky' => TRUE,
      '#empty' => $this->t('We could not find suitable information to display here.', [], $t_options),
      '#sortable' => TRUE,
    ];
  }

  /**
   * Get the names of all parents for the given location.
   */
  private function getLocationParents(array $locations, int $location_id) {
    if (empty($locations[$location_id])) {
      return NULL;
    }
    $parents = [];
    $parent_id = !empty($locations[$location_id]['parent_id']) ? $locations[$location_id]['parent_id'] : NULL;
    while ($parent_id && !empty($locations[$parent_id])) {
      $parent = $locations[$parent_id];
      $parents[] = $parent['name'];
      $parent_id = !empty($parent['parent_id']) ? $parent['parent_id'] : NULL;
    }
    return count($parents) ? array_reverse($parents) : NULL;
  }

}
