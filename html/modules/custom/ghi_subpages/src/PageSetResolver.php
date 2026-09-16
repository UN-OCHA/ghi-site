<?php

namespace Drupal\ghi_subpages;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Resolves operation page creation and ordering from the plan year.
 */
final class PageSetResolver {

  /**
   * Constructs the resolver.
   */
  public function __construct(private readonly ConfigFactoryInterface $configFactory) {
  }

  /**
   * Resolves the single configured page set for a section.
   *
   * @throws \UnexpectedValueException
   *   When the section or page-set configuration is unsupported or invalid.
   */
  public function resolve(SectionNodeInterface $section): PageSet {
    $base_object = $section->getBaseObject();
    if (!$base_object || $base_object->bundle() !== 'plan') {
      throw new \UnexpectedValueException('Operation page sets require a plan section.');
    }
    $year = (int) $base_object->get('field_year')->value;
    if ($year < 1) {
      throw new \UnexpectedValueException('The plan year is required to resolve operation pages.');
    }
    $definitions = $this->configFactory->get('ghi_subpages.page_sets')->get('page_sets') ?? [];
    if (!is_array($definitions) || !$definitions) {
      throw new \UnexpectedValueException('No operation page sets are configured.');
    }

    $matched = [];
    $ranges = [];
    foreach ($definitions as $id => $definition) {
      if (!is_array($definition)) {
        throw new \UnexpectedValueException("Invalid operation page set: $id.");
      }
      $bundles = SubpageManager::SUPPORTED_SUBPAGE_TYPES;
      // Cluster pages are managed outside standard provisioning but still need
      // a slot so their navigation follows the operation page profile.
      $navigation_slots = [...$bundles, 'clusters'];
      $create = $definition['create_on_insert'] ?? [];
      $order = $definition['navigation_order'] ?? [];
      if (!is_array($create) || !is_array($order) || count($create) !== count(array_unique($create)) || count($order) !== count(array_unique($order)) || array_diff($create, $bundles) || array_diff($order, $navigation_slots)) {
        throw new \UnexpectedValueException("Invalid bundles or duplicate slots in operation page set: $id.");
      }
      $from = isset($definition['effective_from']) ? (int) $definition['effective_from'] : PHP_INT_MIN;
      $to = isset($definition['effective_to']) ? (int) $definition['effective_to'] : PHP_INT_MAX;
      if ($from > $to || !isset($definition['base_object_type']) || $definition['base_object_type'] !== 'plan') {
        throw new \UnexpectedValueException("Invalid year range or base object in operation page set: $id.");
      }
      // Check all ranges so a conflicting future profile fails now rather than
      // when the first section for that year is created.
      foreach ($ranges as [$other_from, $other_to]) {
        if ($from <= $other_to && $to >= $other_from) {
          throw new \UnexpectedValueException('Operation page-set year ranges overlap.');
        }
      }
      $ranges[] = [$from, $to];
      if ($year >= $from && $year <= $to) {
        $matched[] = new PageSet((string) $id, $create, $order);
      }
    }
    if (count($matched) !== 1) {
      throw new \UnexpectedValueException("No unique operation page set matches year $year.");
    }
    return $matched[0];
  }

}
