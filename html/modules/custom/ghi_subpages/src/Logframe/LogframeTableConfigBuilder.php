<?php

namespace Drupal\ghi_subpages\Logframe;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Builds standard attachment tables for logframe pages and full exports.
 */
class LogframeTableConfigBuilder {

  use StringTranslationTrait;

  /**
   * Constructs the table configuration builder.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $translation) {
    $this->stringTranslation = $translation;
  }

  /**
   * Builds a configuration-container table item for an attachment prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype
   *   The attachment prototype.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan providing the language for column labels.
   * @param int $id
   *   The configuration-container item ID.
   *
   * @return array
   *   The table item configuration.
   */
  public function build(AttachmentPrototype $prototype, Plan $plan, int $id = 0): array {
    return [
      'id' => $id,
      'item_type' => 'attachment_table',
      'config' => [
        'attachment_prototype' => $prototype->id(),
        'table_form' => [
          'columns' => $prototype->isIndicator() ? $this->buildIndicatorColumns($prototype, $plan) : $this->buildCaseloadColumns($prototype, $plan),
        ],
      ],
    ];
  }

  /**
   * Build caseload columns for an attachment prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $attachment_prototype
   *   The attachment prototype.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object that the attachment prototype belongs to.
   *
   * @return array
   *   Configuration array for table columns compatible with configuration
   *   container items.
   */
  private function buildCaseloadColumns(AttachmentPrototype $attachment_prototype, Plan $plan) {
    $columns = [];
    // Setup the columns.
    $columns[] = [
      'item_type' => 'attachment_label',
      'config' => [
        'label' => NULL,
      ],
      'id' => 0,
    ];
    // Take the in need and target metrics and the preferred measurement.
    $field_types = $attachment_prototype->getFieldTypes();
    $in_need = in_array('in_need', $field_types, TRUE) ? 'in_need' : NULL;
    $target = in_array('target', $field_types, TRUE) ? 'target' : NULL;
    $measure_fields = $attachment_prototype->getMeasurementFields();
    $measure_candidates = [
      'cumulative_reach',
      'latest_reach',
      'periodical_reach',
    ];
    $available_measures = array_intersect($measure_candidates, array_keys($measure_fields));
    $measure = $available_measures ? reset($available_measures) : array_key_first($measure_fields);
    $available_fields = [
      $in_need,
      $target,
      $measure,
    ];
    $available_fields = array_filter($available_fields);
    foreach ($available_fields as $metric_type) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => '',
          'data_point' => [
            'processing' => 'single',
            'calculation' => 'addition',
            'data_points' => [
              0 => [
                'metric_type' => $metric_type,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'metric_type' => NULL,
                'monitoring_period' => 'latest',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if ($measure && $target) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => $attachment_prototype->getDefaultFieldLabel($measure, $plan->getPlanLanguage()) . ' %',
          'data_point' => [
            'processing' => 'calculated',
            'calculation' => 'percentage',
            'data_points' => [
              0 => [
                'metric_type' => $measure,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'metric_type' => $target,
                'monitoring_period' => 'latest',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    return $columns;
  }

  /**
   * Build indicator columns for an attachment prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $attachment_prototype
   *   The attachment prototype.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object that the attachment prototype belongs to.
   *
   * @return array
   *   Configuration array for table columns compatible with configuration
   *   container items.
   */
  private function buildIndicatorColumns(AttachmentPrototype $attachment_prototype, Plan $plan) {
    // Setup the columns.
    $columns = [];
    $columns[] = [
      'item_type' => 'attachment_label',
      'config' => [
        'label' => NULL,
      ],
      'id' => count($columns),
    ];
    $columns[] = [
      'item_type' => 'attachment_unit',
      'config' => [
        'label' => $this->t('Unit', [], ['langcode' => $plan->getPlanLanguage()]),
      ],
      'id' => count($columns),
    ];

    // Take the first metric of type target.
    $field_types = $attachment_prototype->getFieldTypes();
    $target = in_array('target', $field_types, TRUE) ? 'target' : NULL;

    // Take the first available measurement from a pool of valid candidates.
    $measure_candidates = [
      'periodical_measure',
      'measure',
      'cumulative_measure',
    ];
    $available_measures = array_intersect($measure_candidates, $field_types);
    $measure = $available_measures ? reset($available_measures) : NULL;

    // Collect the available fields.
    $available_fields = array_filter([$target, $measure]);
    foreach ($available_fields as $metric_type) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => '',
          'data_point' => [
            'processing' => 'single',
            'calculation' => 'addition',
            'data_points' => [
              0 => [
                'metric_type' => $metric_type,
                'use_calculation_method' => '1',
              ],
              1 => [
                'metric_type' => NULL,
                'use_calculation_method' => '1',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if ($measure) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'spark_line_chart',
        'config' => [
          'label' => $this->t('Progress', [], ['langcode' => $plan->getPlanLanguage()]),
          'data_point' => $measure,
          'baseline' => $target,
          'use_calculation_method' => FALSE,
          'monitoring_periods' => [],
          'show_baseline' => 0,
        ],
      ];
    }
    return $columns;
  }

}
