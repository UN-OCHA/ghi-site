<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'attachment_prototype' fabric query.
 */
#[FabricQuery(
  id: 'attachment_prototype',
  label: new TranslatableMarkup('Attachment prototype query'),
)]
class AttachmentPrototypeQuery extends FabricQueryBase {

  /**
   * The key-value collection for prototype field override definitions.
   */
  public const FIELD_OVERRIDES_KEY_VALUE_COLLECTION = 'ghi_plans.attachment_prototype_field_overrides';

  /**
   * The key-value entry for prototype field override definitions.
   */
  public const FIELD_OVERRIDES_KEY = 'overrides';

  /**
   * The key-value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|null
   */
  protected ?KeyValueFactoryInterface $keyValueFactory = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->keyValueFactory = $container->get('keyvalue');
    return $instance;
  }

  /**
   * Internal helper to query attachment prototypes with the given filters.
   *
   * @param array $filters
   *   An associative array of filters.
   *
   * @return array
   *   The result array from the fabric query or FALSE on failure.
   */
  private function queryWithFilters($filters): array {
    return $this->fabricClient->createQuery('attachmentPrototypes', AttachmentPrototype::getGraphQlItems())
      ->setFilters($filters)
      ->execute() ?: [];

  }

  /**
   * Get an attachment prototype by its id.
   *
   * @param int $prototype_id
   *   The attachment prototype id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The attachment prototype object or NULL if not found.
   */
  public function getPrototype(int $prototype_id): ?AttachmentPrototype {
    $prototype = $this->objectStore->getObject($prototype_id, AttachmentPrototype::getObjectStorageKey());
    if ($prototype) {
      $this->applyConfiguredFieldOverrides([$prototype]);
      return $prototype;
    }

    // Get the attachment data.
    $items = $this->queryWithFilters([
      'Id' => $prototype_id,
    ]);
    if (empty($items)) {
      return NULL;
    }
    $item = reset($items);
    $prototype = $item ? new AttachmentPrototype($item) : NULL;
    if ($prototype) {
      $this->applyConfiguredFieldOverrides([$prototype]);
      $this->objectStore->addObject($prototype);
    }
    return $prototype;
  }

  /**
   * Get attachment prototypes by ids.
   *
   * @param int[] $prototype_ids
   *   An array of attachment prototype ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[]
   *   The attachment prototype object or NULL if not found.
   */
  public function getPrototypes(array $prototype_ids): array {
    $prototype_ids = array_unique($prototype_ids);
    $prototypes = $this->objectStore->getObjects($prototype_ids, AttachmentPrototype::getObjectStorageKey());
    if (count($prototypes) == count($prototype_ids)) {
      $this->applyConfiguredFieldOverrides($prototypes);
      return $prototypes;
    }
    $prototype_ids = array_diff($prototype_ids, array_keys($prototypes));

    // Get the attachment data.
    if (!empty($prototype_ids)) {
      $items = $this->queryWithFilters([
        'Id' => $prototype_ids,
      ]);
      $new_prototypes = array_map(fn ($prototype): AttachmentPrototype => new AttachmentPrototype($prototype), $items);
      $this->objectStore->addObjects($new_prototypes);
      $prototypes += $new_prototypes;
    }
    $this->applyConfiguredFieldOverrides($prototypes);
    return $prototypes;
  }

  /**
   * Get an attachment prototype by plan and prototype ID.
   *
   * @param int $plan_id
   *   The id of the plan to which a prototype belongs.
   * @param int $prototype_id
   *   The id of the prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The processed attachment prototype object.
   */
  public function getPrototypeByPlanAndId(int $plan_id, int $prototype_id): ?AttachmentPrototype {
    // Get the attachment data prototypes.
    $prototypes = $this->getDataPrototypesForPlan($plan_id, FALSE);
    $prototype = $prototypes[$prototype_id] ?? NULL;
    if ($prototype) {
      $this->applyConfiguredFieldOverrides([$prototype]);
    }
    return $prototype;
  }

  /**
   * Get all data attachment prototypes for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param bool $complete_field_definitions
   *   Whether to apply configured field definition overrides.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[]
   *   An array of attachment prototype objects.
   */
  public function getDataPrototypesForPlan(int $plan_id, bool $complete_field_definitions = TRUE): array {
    $prototypes = $this->objectStore->getObjectCollection(AttachmentPrototype::getObjectStorageKey(), 'PlanId', $plan_id);
    if (!empty($prototypes)) {
      if ($complete_field_definitions) {
        $this->applyConfiguredFieldOverrides($prototypes);
      }
      return $prototypes;
    }
    // Get the attachment data.
    $items = $this->queryWithFilters([
      'PlanId' => $plan_id,
      'Type' => AttachmentPrototype::DATA_TYPES,
    ]);
    $prototypes = $this->buildResultObjects($items, AttachmentPrototype::class);
    if ($complete_field_definitions) {
      $this->applyConfiguredFieldOverrides($prototypes);
    }
    $this->objectStore->addObjectCollection($prototypes, AttachmentPrototype::getObjectStorageKey(), 'PlanId');
    return $prototypes;
  }

  /**
   * Get all data attachment prototypes for the given plan.
   *
   * @param int[] $plan_ids
   *   The plan ids.
   * @param bool $complete_field_definitions
   *   Whether to apply configured field definition overrides.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[][]
   *   An array of arrays of attachment prototype objects, keyed by plan id.
   */
  public function getDataPrototypesForPlans(array $plan_ids, bool $complete_field_definitions = TRUE): array {
    sort($plan_ids);

    $plan_ids = array_unique($plan_ids);

    $prototypes = [];
    foreach ($plan_ids as $plan_id) {
      $prototypes += $this->objectStore->getObjectCollection(AttachmentPrototype::getObjectStorageKey(), 'PlanId', $plan_id);
    }
    $existing_plan_ids = array_unique(array_map(fn ($prototype) => $prototype->getPlanId(), $prototypes));
    $plan_ids = array_diff($plan_ids, $existing_plan_ids);

    // Fetch the remaining prototypes.
    if (!empty($plan_ids)) {
      $items = $this->queryWithFilters([
        'PlanId' => $plan_ids,
        'Type' => AttachmentPrototype::DATA_TYPES,
      ]);
      /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[] $prototypes */
      $new_prototypes = $this->buildResultObjects($items, AttachmentPrototype::class);
      if ($complete_field_definitions) {
        $this->applyConfiguredFieldOverrides($new_prototypes);
      }
      $this->objectStore->addObjectCollection($new_prototypes, AttachmentPrototype::getObjectStorageKey(), 'PlanId');
      $prototypes += $new_prototypes;
    }

    if ($complete_field_definitions) {
      $this->applyConfiguredFieldOverrides($prototypes);
    }
    $prototypes_by_plan = [];
    foreach ($prototypes as $prototype) {
      $plan_id = $prototype->getPlanId();
      $prototypes_by_plan[$plan_id] = $prototypes_by_plan[$plan_id] ?? [];
      $prototypes_by_plan[$plan_id][$prototype->id()] = $prototype;
    }
    return $prototypes_by_plan;
  }

  /**
   * Apply configured prototype field overrides.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[] $prototypes
   *   The prototypes to update.
   */
  private function applyConfiguredFieldOverrides(array $prototypes): void {
    $data_prototypes = array_filter($prototypes, function ($prototype) {
      return $prototype instanceof AttachmentPrototype && AttachmentPrototype::isDataType($prototype->getRawData());
    });
    if (empty($data_prototypes)) {
      return;
    }

    $overrides = $this->getConfiguredFieldOverrides();
    if (empty($overrides)) {
      return;
    }

    foreach ($data_prototypes as $prototype) {
      $prototype_overrides = $overrides[$prototype->id()] ?? [];
      foreach ($prototype_overrides['additions'] ?? [] as $addition) {
        $this->applyConfiguredFieldAddition($prototype, $addition);
      }
      foreach ($prototype_overrides['replacements'] ?? [] as $index => $replacement) {
        $this->applyConfiguredFieldReplacement($prototype, (int) $index, $replacement);
      }
    }
  }

  /**
   * Apply a configured field addition to a prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype
   *   The prototype to update.
   * @param array $addition
   *   The normalized field addition definition.
   */
  private function applyConfiguredFieldAddition(AttachmentPrototype $prototype, array $addition): void {
    $metric_type = $this->getMetricTypeByMachineName($addition['metric_type']);
    if (!$metric_type) {
      return;
    }
    $prototype->addMissingMetricTypeField($metric_type, $addition['field_group'], $addition['label'] ?? NULL);
  }

  /**
   * Apply a configured field replacement to a prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype
   *   The prototype to update.
   * @param int $index
   *   The original prototype field index to replace.
   * @param array $replacement
   *   The normalized field replacement definition.
   */
  private function applyConfiguredFieldReplacement(AttachmentPrototype $prototype, int $index, array $replacement): void {
    if (!$prototype->getFieldDefinitionByOriginalIndex($index)) {
      return;
    }

    $metric_type = $this->getMetricTypeByMachineName($replacement['metric_type']);
    if (!$metric_type) {
      return;
    }
    $prototype->replaceMetricTypeField($index, $metric_type, $replacement['field_group'], $replacement['label'] ?? NULL);
  }

  /**
   * Get normalized configured field overrides.
   *
   * @return array
   *   The field overrides keyed by prototype id.
   */
  private function getConfiguredFieldOverrides(): array {
    if (!$this->keyValueFactory) {
      return [];
    }

    $overrides = $this->keyValueFactory
      ->get(self::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->get(self::FIELD_OVERRIDES_KEY, []);
    return self::normalizeFieldOverrides($overrides);
  }

  /**
   * Normalize prototype field overrides from the key-value store.
   *
   * @param array $overrides
   *   The raw field overrides.
   *
   * @return array
   *   The normalized field overrides keyed by prototype id.
   */
  public static function normalizeFieldOverrides(array $overrides): array {
    $normalized = [];
    foreach ($overrides['additions'] ?? [] as $prototype_id => $additions) {
      if (!is_array($additions)) {
        $additions = [$additions];
      }
      elseif (array_key_exists('metric_type', $additions)) {
        $additions = [$additions];
      }
      elseif (!array_is_list($additions)) {
        continue;
      }
      $prototype_id = (int) $prototype_id;
      foreach ($additions as $addition) {
        $addition = is_string($addition) ? ['metric_type' => $addition] : $addition;
        if (!is_array($addition) || empty($addition['metric_type']) || !is_string($addition['metric_type'])) {
          continue;
        }
        $field_group = self::normalizeFieldGroup(is_string($addition['field_group'] ?? NULL) ? $addition['field_group'] : AttachmentPrototype::FIELD_GROUP_MEASUREMENT);
        if (!$field_group) {
          continue;
        }
        $normalized[$prototype_id]['additions'][] = [
          'metric_type' => $addition['metric_type'],
          'field_group' => $field_group,
        ];
        if (!empty($addition['label']) && is_string($addition['label'])) {
          $normalized[$prototype_id]['additions'][array_key_last($normalized[$prototype_id]['additions'])]['label'] = $addition['label'];
        }
      }
    }

    foreach ($overrides['replacements'] ?? [] as $prototype_id => $replacements) {
      if (!is_array($replacements)) {
        continue;
      }
      $prototype_id = (int) $prototype_id;
      foreach ($replacements as $index => $replacement) {
        $replacement = is_string($replacement) ? ['metric_type' => $replacement] : $replacement;
        if (!is_array($replacement) || empty($replacement['metric_type']) || !is_string($replacement['metric_type']) || !is_numeric($index)) {
          continue;
        }
        $field_group = self::normalizeFieldGroup(is_string($replacement['field_group'] ?? NULL) ? $replacement['field_group'] : AttachmentPrototype::FIELD_GROUP_MEASUREMENT);
        if (!$field_group) {
          continue;
        }
        $normalized[$prototype_id]['replacements'][(int) $index] = [
          'metric_type' => $replacement['metric_type'],
          'field_group' => $field_group,
        ];
        if (!empty($replacement['label']) && is_string($replacement['label'])) {
          $normalized[$prototype_id]['replacements'][(int) $index]['label'] = $replacement['label'];
        }
      }
    }
    return $normalized;
  }

  /**
   * Normalize a prototype field group value.
   *
   * @param string|null $field_group
   *   The field group value.
   *
   * @return string|null
   *   The normalized field group, or NULL if unsupported.
   */
  public static function normalizeFieldGroup(?string $field_group): ?string {
    return in_array($field_group, self::getSupportedFieldGroups(), TRUE) ? $field_group : NULL;
  }

  /**
   * Get supported prototype field groups.
   *
   * @return string[]
   *   The supported field groups.
   */
  public static function getSupportedFieldGroups(): array {
    return [
      AttachmentPrototype::FIELD_GROUP_PLANNING,
      AttachmentPrototype::FIELD_GROUP_MEASUREMENT,
    ];
  }

  /**
   * Get the configured field overrides as editable JSON data.
   *
   * @return array
   *   The raw field overrides.
   */
  public function getEditableFieldOverrides(): array {
    return $this->keyValueFactory
      ? $this->keyValueFactory->get(self::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)->get(self::FIELD_OVERRIDES_KEY, [])
      : [];
  }

  /**
   * Save the configured field overrides.
   *
   * @param array $overrides
   *   The raw field overrides.
   */
  public function setEditableFieldOverrides(array $overrides): void {
    if (!$this->keyValueFactory) {
      return;
    }
    $this->keyValueFactory
      ->get(self::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->set(self::FIELD_OVERRIDES_KEY, $overrides);
  }

}
