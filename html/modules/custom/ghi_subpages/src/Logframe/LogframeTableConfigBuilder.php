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
    // Keep the preferred measurement consistent across pages and exports.
    $field_types = $attachment_prototype->getFieldTypes();
    $in_need = array_search('in_need', $field_types);
    $target = array_search('target', $field_types);
    $measure_fields = $attachment_prototype->getMeasurementFields();
    $measure_keys = array_keys($measure_fields);
    $measure = count($measure_keys) ? ($measure_keys[1] ?? end($measure_keys)) : NULL;
    $available_fields = [
      $in_need,
      $target,
      $measure,
    ];
    $available_fields = array_filter($available_fields, function ($field) {
      return is_int($field);
    });
    foreach ($available_fields as $index) {
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
                'index' => $index,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'index' => '0',
                'monitoring_period' => 'latest',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if (is_int($measure) && is_int($target)) {
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
                'index' => $measure,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'index' => $target,
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
    $target = array_search('target', $field_types);

    // Take the last measurement from a pool of valid candidates.
    $field_types_reversed = array_reverse($field_types, TRUE);
    $measure_candidates = [
      'periodical_measure',
      'measure',
      'cumulative_measure',
    ];
    $measure = FALSE;
    foreach ($measure_candidates as $measure_candidate) {
      $measure = array_search($measure_candidate, $field_types_reversed);
      if ($measure !== FALSE) {
        break;
      }
    }

    // Collect the available fields.
    $available_fields = array_filter([$target, $measure], function ($field) {
      return is_int($field);
    });
    foreach ($available_fields as $index) {
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
                'index' => $index,
                'use_calculation_method' => '1',
              ],
              1 => [
                'index' => '0',
                'use_calculation_method' => '1',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if (is_int($measure)) {
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
