<?php

namespace Drupal\ghi_blocks\Services;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\FinancialLabel;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cleans up financial table label overrides in Layout Builder sections.
 */
final class FinancialLabelUpdater implements ContainerInjectionInterface {

  private const ENTITY_BATCH_SIZE = 50;

  private const EMPTY_CHANGES = [
    'removed' => 0,
    'stripped' => 0,
  ];

  private const CURRENT_LAYOUT_TABLES = [
    'node__layout_builder__layout' => 'node',
    'page_template__layout_builder__layout' => 'page_template',
  ];

  /**
   * Unit-bearing financial data types and their unit-free defaults.
   */
  private const FINANCIAL_LABELS = [
    'project_funding' => [
      'original_requirements' => [
        'label' => 'Original requirements',
        'context' => FinancialLabel::TRANSLATION_CONTEXT,
      ],
      'current_requirements' => [
        'label' => 'Requirements',
        'context' => FinancialLabel::TRANSLATION_CONTEXT,
      ],
      'total_funding' => [
        'label' => 'Funding',
      ],
      'requirements_changes' => [
        'label' => 'Requirements changes',
      ],
    ],
    'funding_data' => [
      'funding_totals' => [
        'label' => 'Funding',
      ],
      'outside_funding' => [
        'label' => 'Funded outside HRP',
      ],
      'funding_gap' => [
        'label' => 'Unmet',
      ],
      'original_requirements' => [
        'label' => 'Original requirements',
        'context' => FinancialLabel::TRANSLATION_CONTEXT,
      ],
      'current_requirements' => [
        'label' => 'Requirements',
        'context' => FinancialLabel::TRANSLATION_CONTEXT,
      ],
    ],
  ];

  /**
   * Loaded plans, keyed by source ID.
   *
   * @var array<int, \Drupal\ghi_plans\Entity\Plan|null>
   */
  private array $plans = [];

  /**
   * Constructs a financial label updater.
   */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager, private readonly TranslationInterface $stringTranslation, private readonly Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('database'),
    );
  }

  /**
   * Gets current-layout entities containing supported financial items.
   *
   * @return array<int, array{entity_type_id: string, entity_id: int}>
   *   Entity type and ID pairs suitable for command batching.
   */
  public function getEntityReferences(): array {
    $references = [];
    foreach (self::CURRENT_LAYOUT_TABLES as $table => $entity_type_id) {
      if (!$this->database->schema()->tableExists($table) || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }

      // Layout sections are serialized, so entity storage property lookups
      // cannot identify configurations containing these item plugin IDs.
      $query = $this->database->select($table)
        ->fields($table, ['entity_id']);
      $financial_items = $query->orConditionGroup()
        ->condition('layout_builder__layout_section', '%' . $this->database->escapeLike('project_funding') . '%', 'LIKE')
        ->condition('layout_builder__layout_section', '%' . $this->database->escapeLike('funding_data') . '%', 'LIKE');
      $entity_ids = $query
        ->condition($financial_items)
        ->orderBy('entity_id')
        ->distinct()
        ->execute()
        ->fetchAll(FetchAs::Column);
      foreach ($entity_ids as $entity_id) {
        $references[] = [
          'entity_type_id' => $entity_type_id,
          'entity_id' => (int) $entity_id,
        ];
      }
    }
    return $references;
  }

  /**
   * Updates and saves the referenced current-layout entities in batches.
   *
   * @param array<int, array{entity_type_id: string, entity_id: int}> $references
   *   Entity type and ID pairs returned by self::getEntityReferences().
   * @param callable|null $progress
   *   An optional callback invoked after each processed reference.
   *
   * @return array{processed: int, updated_entities: int, removed: int, stripped: int, missing: int}
   *   Processing and label-change totals.
   */
  public function updateEntityReferences(array $references, ?callable $progress = NULL): array {
    $results = [
      'processed' => 0,
      'updated_entities' => 0,
      'removed' => 0,
      'stripped' => 0,
      'missing' => 0,
    ];
    $references_by_entity_type = [];
    foreach ($references as $reference) {
      $references_by_entity_type[$reference['entity_type_id']][] = $reference;
    }

    foreach ($references_by_entity_type as $entity_type_id => $entity_references) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      foreach (array_chunk($entity_references, self::ENTITY_BATCH_SIZE) as $batch) {
        $entity_ids = array_column($batch, 'entity_id');
        $entities = $storage->loadMultiple($entity_ids);
        foreach ($batch as $reference) {
          $entity = $entities[$reference['entity_id']] ?? NULL;
          if (!$entity instanceof ContentEntityInterface) {
            $results['missing']++;
          }
          else {
            $changes = $this->updateEntity($entity);
            if (array_sum($changes) > 0) {
              // Updating the loaded revision and marking the save as a sync
              // are separate safeguards: the former avoids creating editorial
              // revisions, while the latter lets save hooks suppress side
              // effects intended for editorial changes.
              if ($entity->getEntityType()->isRevisionable()) {
                $entity->setNewRevision(FALSE);
              }
              $entity->setSyncing(TRUE);
              $entity->save();
              $results['updated_entities']++;
              $results['removed'] += $changes['removed'];
              $results['stripped'] += $changes['stripped'];
            }
          }
          $results['processed']++;
          if ($progress !== NULL) {
            $progress();
          }
        }

        // Avoid retaining Layout Builder component graphs for the duration of
        // a potentially long-running command.
        $storage->resetCache($entity_ids);
        unset($entities);
        gc_collect_cycles();
      }
    }
    return $results;
  }

  /**
   * Cleans up financial labels in an entity's Layout Builder sections.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A node or page template containing Layout Builder sections.
   *
   * @return array{removed: int, stripped: int}
   *   Counts of redundant overrides removed and custom labels stripped.
   */
  public function updateEntity(ContentEntityInterface $entity): array {
    $changes = self::EMPTY_CHANGES;
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return $changes;
    }

    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach ($sections as &$section_item) {
      $section = $section_item['section'] ?? NULL;
      if (!$section instanceof Section) {
        continue;
      }
      foreach ($this->updateSection($section) as $type => $count) {
        $changes[$type] += $count;
      }
    }
    unset($section_item);

    if (array_sum($changes) > 0) {
      $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    }
    return $changes;
  }

  /**
   * Cleans up financial labels in a Layout Builder section.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to update.
   *
   * @return array{removed: int, stripped: int}
   *   Counts of redundant overrides removed and custom labels stripped.
   */
  public function updateSection(Section $section): array {
    $changes = self::EMPTY_CHANGES;
    foreach ($section->getComponents() as $component) {
      $configuration = $component->get('configuration');
      $columns_path = $this->getColumnsPath($configuration);
      if ($columns_path === NULL) {
        continue;
      }

      $columns = NestedArray::getValue($configuration, $columns_path);
      $original_columns = $columns;
      $plan = $this->getPlan($configuration);
      foreach ($columns as &$column) {
        $item_type = $column['item_type'] ?? NULL;
        $data_type = $column['config']['data_type'] ?? NULL;
        $definition = is_string($item_type) && is_string($data_type) ? self::FINANCIAL_LABELS[$item_type][$data_type] ?? NULL : NULL;
        $label = $column['config']['label'] ?? NULL;
        if ($definition === NULL || !is_string($label) || trim($label) === '') {
          continue;
        }

        [$unit_free_label, $unit_was_stripped] = $this->stripCurrencyUnit($label);
        $expected_label = $plan ? (string) $this->stringTranslation->translate(
          $definition['label'],
          [],
          $plan->getTranslationOptions($definition['context'] ?? NULL),
        ) : NULL;
        // Remove an override only when its unit-free wording matches the
        // localized default. If plan context is unavailable, retain the custom
        // wording and remove only the obsolete currency suffix.
        if ($unit_free_label === '' || ($expected_label !== NULL && $this->normalizeLabel($unit_free_label) === $this->normalizeLabel($expected_label))) {
          unset($column['config']['label']);
          $changes['removed']++;
        }
        elseif ($unit_was_stripped) {
          $column['config']['label'] = $unit_free_label;
          $changes['stripped']++;
        }
      }
      unset($column);

      if ($columns !== $original_columns) {
        NestedArray::setValue($configuration, $columns_path, $columns);
        $component->setConfiguration($configuration);
      }
    }
    return $changes;
  }

  /**
   * Gets the persisted path to columns with or without the HPC config wrapper.
   */
  private function getColumnsPath(array $configuration): ?array {
    foreach ([['hpc', 'table', 'columns'], ['table', 'columns']] as $path) {
      $key_exists = FALSE;
      $columns = NestedArray::getValue($configuration, $path, $key_exists);
      if ($key_exists && is_array($columns)) {
        return $path;
      }
    }
    return NULL;
  }

  /**
   * Loads and caches the plan referenced by a block's plan context mapping.
   */
  private function getPlan(array $configuration): ?Plan {
    $mapping = $configuration['context_mapping']['plan'] ?? $configuration['contexts']['context_mapping']['plan'] ?? NULL;
    if (!is_string($mapping) || !preg_match('/^plan--(\d+)$/', $mapping, $matches)) {
      return NULL;
    }

    $source_id = (int) $matches[1];
    if (!array_key_exists($source_id, $this->plans)) {
      $plans = $this->entityTypeManager->getStorage('base_object')->loadByProperties([
        'type' => Plan::BUNDLE,
        'field_original_id' => $source_id,
      ]);
      $plan = reset($plans);
      $this->plans[$source_id] = $plan instanceof Plan ? $plan : NULL;
    }
    return $this->plans[$source_id];
  }

  /**
   * Removes a terminal currency suffix while preserving custom wording.
   *
   * @return array{0: string, 1: bool}
   *   The trimmed label and whether a currency suffix was removed.
   */
  private function stripCurrencyUnit(string $label): array {
    $count = 0;
    $label = preg_replace('/[\p{Z}\s]*\(\$\)[\p{Z}\s]*$/u', '', $label, -1, $count) ?? $label;
    return [trim($label), $count > 0];
  }

  /**
   * Normalizes whitespace and case for a conservative label comparison.
   *
   * Punctuation and wording remain significant so editorial changes survive.
   */
  private function normalizeLabel(string $label): string {
    $label = preg_replace('/[\p{Z}\s]+/u', ' ', $label) ?? $label;
    return mb_strtolower(trim($label));
  }

}
