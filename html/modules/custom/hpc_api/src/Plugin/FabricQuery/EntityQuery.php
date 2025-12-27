<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of a generic 'entity' fabric query.
 *
 * Under the hood, this will do entity type specific queries to get data from
 * fabric.
 */
#[FabricQuery(
  id: 'entity',
  label: new TranslatableMarkup('Entity query'),
)]
class EntityQuery extends FabricQueryBase {

  /**
   * Retrieve data about an entity.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return object|null
   *   The result object if any.
   */
  public function getEntityData(int $entity_type_id, int $entity_id): ?object {
    $query_definitions = $this->getEntityQueryDefinitions();
    $entity_type = $this->getEntityTypeById($entity_type_id);
    $query_definition = $query_definitions[$entity_type->getName()] ?? NULL;
    if (!$query_definition) {
      return NULL;
    }
    $query = $this->getEntityQuery($entity_type_id, $entity_id);
    if (!$query) {
      return NULL;
    }
    $data = $this->fabricQuery->query($query);
    $items = $data ? $this->getItems($data, $query_definition['namespace'], $query_definition['primary_key']) : [];
    return $items[$entity_id] ?? NULL;
  }

  /**
   * Get entity query definitions.
   *
   * @return array[]
   *   Definitions of entity queries to use for data lookup. Keyed by the
   *   entity type name.
   */
  private function getEntityQueryDefinitions(): array {
    $logframe_entity = [
      'namespace' => 'logframeEntities',
      'primary_key' => 'Id',
      'items' => [
        'Id',
        'Name',
        'NamePrefix',
        'Description',
        'EntityTypeId',
        'PlanId',
        'HpcEntityPrototypeId',
        'HpcId',
        'HpcVersionId',
        'IsLocked',
        'CustomReference',
        'ComposedReference',
        'SortOrder',
        'VisibilityGroupId',
        'CreatedAt',
        'UpdatedAt',
        'RecordStatus',
        'ActiveUntil',
      ],
    ];
    return [
      'Plan' => [
        'namespace' => 'plans',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'ShortName',
          'PlanSubTitle',
          'PlanCode',
          'PlanType',
          'PlanClusterType',
          'PlanCosting',
          'PlanLanguage',
          'PlanLanguageCode',
          'IsPartOfGHO',
          'IsForHPCProjects',
          'IsReleased',
          'ReleasedDate',
          'RevisionState',
          'IsRestricted',
          'CustomLocationCode',
          'FocusedLocationId',
          'FocusedLocationName',
          'DocumentPublishDate',
          'Description',
          'StartDate',
          'EndDate',
          'HpcId',
          'HpcVersionId',
          'VisibilityGroupId',
          'RecordStatus',
          'CreatedAt',
          'UpdatedAt',
          'Source',
          'SourceId',
          'ActiveUntil',
          'IsLocked',
        ],
      ],
      'Project' => [
        'namespace' => 'projects',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'ProjectCode',
          'Description',
          'Objective',
          'StartDate',
          'EndDate',
          'CurrentRequestedFunds',
          'ImplementingPartners',
          'ImplementationStatus',
          'PgSqlPdf',
          'VisibilityGroupId',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
          'IsLocked',
        ],
      ],
      'Location' => [
        'namespace' => 'locations',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'AdminLevel',
          'ISO3',
          'Pcode',
          'Description',
          'Latitude',
          'Longitude',
          'RecordStatus',
          'IsLocked',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'Organization' => [
        'namespace' => 'organizations',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'Description',
          'Abbreviation',
          'url',
          'NativeName',
          'Comments',
          'NewOrganizationId',
          'HpcId',
          'IsLocked',
          'CollectiveInd',
          'IsVerified',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'FieldCluster' => [
        'namespace' => 'coordinationEntities',
        'primary_key' => 'HpcId',
        'items' => [
          'Id',
          'Name',
          'Description',
          'HpcEntityPrototypeId',
          'IsOverriding',
          'HpcId',
          'HpcVersionId',
          'VisibilityGroupId',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'Period' => [
        'namespace' => 'periods',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'PeriodType',
          'StartDate',
          'EndDate',
          'CalendarYear',
          'Description',
          'HpcId',
          'IsLocked',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'StrategicObjective' => $logframe_entity,
      'SpecificObjective' => $logframe_entity,
      'ClusterObjective' => $logframe_entity,
      'ClusterActivity' => $logframe_entity,
    ];
  }

  /**
   * Get the query used to query entity data.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return string|null
   *   The query payload for fabric.
   */
  public function getEntityQuery(int $entity_type_id, int $entity_id): ?string {
    $query_definitions = $this->getEntityQueryDefinitions();
    $entity_type = $this->getEntityTypeById($entity_type_id);
    $query_definition = $query_definitions[$entity_type->getName()] ?? NULL;
    if (!$query_definition) {
      return NULL;
    }
    $namespace = $query_definition['namespace'];
    $primary_key = $query_definition['primary_key'];
    $items = implode(' ', $query_definition['items']);
    return "{$namespace} (filter: { {$primary_key}: { eq: {$entity_id} } }) { items { {$items} }}";
  }

}
