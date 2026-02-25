<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery as AttributeFabricQuery;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of a generic 'entity' fabric query.
 *
 * Under the hood, this will do entity type specific queries to get data from
 * fabric.
 */
#[AttributeFabricQuery(
  id: 'entity_lookup',
  label: new TranslatableMarkup('Entity query'),
)]
class EntityLookupQuery extends FabricQueryBase {

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
        'CoordinationEntityId',
        'HpcEntityPrototypeId',
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
          'StartDate',
          'EndDate',
          'Objective',
          'VisibilityGroupId',
          'ImplementingPartners',
          'ImplementationStatus',
          'CurrentRequestedFunds',
          'RecordStatus',
          'ActiveUntil',
          'Source',
          'SourceId',
          'PlanId',
          'CreatedAt',
          'IsPublished',
          'UpdatedAt',
          'IsLocked',
          'PgSqlPdf',
          'HpcId',
          'HpcVersionId',
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
          'IsLocked',
          'CollectiveInd',
          'IsVerified',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'Sector' => [
        'namespace' => 'sectors',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'SectorType',
          'SectorCode',
          'Description',
          'VisibilityGroupId',
          'IsLocked',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
      'FieldCluster' => [
        'namespace' => 'coordinationEntities',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'Description',
          'HpcEntityPrototypeId',
          'IsOverriding',
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
      'Contact' => [
        'namespace' => 'contacts',
        'primary_key' => 'Id',
        'items' => [
          'Id',
          'Name',
          'Email',
          'Phone',
          'IsLocked',
          'RecordStatus',
          'ActiveUntil',
          'CreatedAt',
          'UpdatedAt',
        ],
      ],
    ];
  }

  /**
   * Get options for entity queries.
   *
   * @return array
   *   An array of entity query options. Keyed by the namespace, values are the
   *   labels.
   */
  public function getEntityQueryOptions() {
    return array_flip(array_map(fn ($item) => $item['namespace'], $this->getEntityQueryDefinitions()));
  }

  /**
   * Get the query used to query entity data.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\hpc_api\Query\FabricQuery|null
   *   A fabric query object or NULL.
   */
  public function getEntityQuery(int $entity_type_id, int $entity_id): ?FabricQuery {
    $query_definitions = $this->getEntityQueryDefinitions();
    $entity_type = $this->getEntityTypeById($entity_type_id);
    $query_definition = $query_definitions[$entity_type->getName()] ?? NULL;
    if (!$query_definition) {
      return NULL;
    }
    $namespace = $query_definition['namespace'];
    $primary_key = $query_definition['primary_key'];
    $items = $query_definition['items'];
    return $this->fabricClient->createQuery($namespace, $items, [$primary_key => $entity_id]);
  }

}
