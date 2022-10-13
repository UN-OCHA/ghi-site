<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for disaggregation modals.
 */
class DissaggregationModalController extends ControllerBase {

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
  private function modalTitle(DataAttachment $attachment, $metric, $reporting_period) {
    $metrics = $attachment->getMetricFields();
    $entity = $attachment->getSourceEntity();
    $icon = $entity instanceof GoverningEntity ? $entity->icon : NULL;
    $title = '';
    if ($icon && $icon_embed = $this->iconQuery->getIconEmbedCode($icon)) {
      $title = $icon_embed;
    }
    $title .= $metrics[$metric];

    if ($attachment->isMeasurementField($metrics[$metric])) {
      $title .= ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $attachment->getReportingPeriod($attachment->getPlanId(), $reporting_period),
        '#format_string' => '<span class="title-additional-info">Monitoring period @period_number: @date_range</span>',
      ], FALSE);
    }

    return Markup::create($title);
  }

  /**
   * Load JSON data for a disaggregation modal window.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param int $metric
   *   The index of the metric item.
   * @param int $reporting_period
   *   The reporting period id for which to retrieve the data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A response object.
   */
  public function loadDisaggregationModalData(DataAttachment $attachment, $metric, $reporting_period) {
    $cid = implode('-', [
      __FUNCTION__,
      $attachment->id(),
      $metric,
      $reporting_period,
    ]);
    $cache = $this->cache();
    $cached_build = $cache->get($cid);
    if ($cached_build) {
      $build = $cached_build->data;
    }
    else {
      $build = $this->buildDisaggregationModalContent($attachment, $metric, $reporting_period);
      $cache->set($cid, $build);
    }
    return [
      '#type' => 'container',
      '#attached' => [
        'library' => ['ghi_blocks/disaggregation_modal'],
        'drupalSettings' => [
          'disaggregation_modal_title' => $this->modalTitle($attachment, $metric, $reporting_period),
        ],
      ],
      'content' => $build,
    ];
  }

  /**
   * Build content for a disaggregation modal window.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param int $metric
   *   The index of the metric item.
   * @param int $reporting_period
   *   The reporting period id for which to retrieve the data.
   *
   * @return array
   *   A render array.
   */
  private function buildDisaggregationModalContent(DataAttachment $attachment, $metric, $reporting_period) {

    $unit_type = $attachment->getUnitType();

    $unit_defaults = [
      'amount' => [
        '#scale' => 'full',
      ],
    ];

    $decimal_format = NULL;
    $plan_id = $attachment->getPlanId();
    if ($plan_id && $plan_object = BaseObjectHelper::getBaseObjectFromOriginalId($plan_id, 'plan')) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
      $decimal_format = $plan_object->getDecimalFormat();
    }

    // Retrieve disaggregated data form the attachment. The results is a
    // multi-dimensional array keyed by the metric in the first level. Each
    // metric contains the relevant location and catagory data.
    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period, TRUE, TRUE, TRUE);
    if (empty($disaggregated_data[$metric])) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('We did not find the requested information. If you think that this ann error, please get in touch.'),
      ];
    }

    if (empty($disaggregated_data[$metric]['locations'])) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('We could not find suitable information to display here.'),
      ];
    }

    // All locations have the same categories, so take the first one and
    // extract them to create the header.
    $categories = $attachment->getDisaggregatedCategories($reporting_period, $metric, TRUE, TRUE);

    // Build the table.
    $header = [
      $this->t('Location'),
    ];
    if (!empty($categories)) {
      foreach ($categories as $category) {
        $header[] = $category;
      }
    }
    if (empty($categories)) {
      $header[] = $this->t('Totals');
    }

    // Go over the data and create the table rows.
    $rows = [];
    $totals = [];

    // Key by location ID.
    $locations = [];
    foreach ($disaggregated_data[$metric]['locations'] as $location) {
      $locations[] = $location;
    }

    foreach ($locations as $location) {
      $row = [];
      $parents = array_key_exists('id', $location) ? $this->getLocationParents($locations, $location['id']) : NULL;
      if (!$parents || !is_array($parents)) {
        $row[] = $location['name'];
      }
      else {
        $parents[] = $location['name'];
        $row[] = implode(' > ', $parents);
      }

      if (!empty($location['categories'])) {
        foreach ($location['categories'] as $category) {
          $row[] = [
            'data' => [
              '#theme' => 'hpc_autoformat_value',
              '#value' => $category['data'],
              '#unit_type' => $unit_type,
              '#unit_defaults' => $unit_defaults,
              '#decimal_format' => $decimal_format,
            ],
          ];
        }
      }

      // Add the location total to the row as the last item if there are no
      // categories.
      if (empty($categories)) {
        $row[] = [
          'data' => [
            '#theme' => 'hpc_autoformat_value',
            '#value' => $location['total'],
            '#unit_type' => $unit_type,
            '#unit_defaults' => $unit_defaults,
            '#decimal_format' => $decimal_format,
          ],
        ];
        $totals[] = $location['total'];
      }

      $rows[] = $row;
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('We could not find suitable information to display here.'),
    ];
  }

  /**
   * Get the names of all parents for the given location.
   */
  private function getLocationParents($locations, $location_id) {
    if (empty($locations[$location_id])) {
      return NULL;
    }
    $parents = [];
    $parent_id = !empty($locations[$location_id]['map_data']['parent_id']) ? $locations[$location_id]['map_data']['parent_id'] : NULL;
    while ($parent_id && !empty($locations[$parent_id])) {
      $parent = $locations[$parent_id];
      $parents[] = $parent['name'];
      $parent_id = !empty($parent['map_data']['parent_id']) ? $parent['map_data']['parent_id'] : NULL;
    }
    return count($parents) ? array_reverse($parents) : NULL;
  }

}
