<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API data attachment objects.
 */
class CaseloadAttachment extends DataAttachment implements CaseloadAttachmentInterface {

  /**
   * Get a caseload value.
   *
   * @param string $metric_type
   *   The metric type.
   * @param string $metric_name
   *   The english metric name.
   * @param string $fallback_type
   *   The metric type of a fallback.
   *
   * @return int
   *   The caseload value if found.
   */
  public function getCaseloadValue($metric_type, $metric_name = NULL, $fallback_type = NULL) {
    $caseload_item = $this->getCaseloadItemByType($metric_type);
    if (!$caseload_item && $metric_name !== NULL) {
      // Fallback, see https://humanitarian.atlassian.net/browse/HPC-7838
      $caseload_item = $this->getCaseloadItemByName($metric_name);
    }
    if ($caseload_item && property_exists($caseload_item, 'value')) {
      $value = $caseload_item->value;
      return $value !== NULL ? (int) $caseload_item->value : NULL;
    }
    if ($fallback_type !== NULL) {
      return $this->getCaseloadValue($fallback_type);
    }
    return NULL;
  }

  /**
   * Get a caseload item by metric type.
   *
   * @param string $type
   *   The metric type.
   *
   * @return object|null
   *   A caseload item if found.
   */
  private function getCaseloadItemByType($type) {
    $caseload_items = $this->getOriginalFields();

    if (!$caseload_items) {
      return NULL;
    }

    $candidates = array_filter($caseload_items, function ($item) use ($type) {
      return (strtolower($item->type) == strtolower($type));
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
  }

  /**
   * Get a caseload item by metric name.
   *
   * @param string $name
   *   The metric name.
   *
   * @return object|null
   *   A caseload item if found.
   */
  private function getCaseloadItemByName($name) {
    $caseload_items = $this->getOriginalFields();
    if (!$caseload_items) {
      return NULL;
    }

    // We support alternative names based on RPM.
    $alternative_names = [
      // Reached.
      'Reached' => [
        'Atteints',
        'Personas Atendidas',
      ],
      // Cumulative reach.
      'Cumulative reach' => [
        'Cumul atteint',
        'Alcance cumulativo',
      ],
      // Covered.
      'Covered' => [
        'Couverts',
        'Personas con Necesidades Cubiertas',
      ],
    ];

    $candidates = array_filter($caseload_items, function ($item) use ($name, $alternative_names) {
      if (!property_exists($item->name, 'en')) {
        return FALSE;
      }
      $item_name = $item->name->en;
      if ($item_name == $name) {
        return TRUE;
      }
      if (array_key_exists($name, $alternative_names) && in_array($item_name, $alternative_names[$name])) {
        return TRUE;
      }
      return FALSE;
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
  }

}
