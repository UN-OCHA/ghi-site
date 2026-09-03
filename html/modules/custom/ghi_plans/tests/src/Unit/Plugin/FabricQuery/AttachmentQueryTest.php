<?php

namespace Drupal\Tests\ghi_plans\Unit\Plugin\FabricQuery;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\hpc_api\ObjectStore;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the attachment Fabric query plugin.
 *
 * @group ghi_plans
 */
class AttachmentQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    drupal_static_reset('getQueryInstance');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    drupal_static_reset('getQueryInstance');
    parent::tearDown();
  }

  /**
   * Tests that plan attachments are filtered by the current base object.
   */
  public function testGetAttachmentsForPlanFiltersByBaseObjectContext(): void {
    $context_object = $this->createMock(BaseObjectInterface::class);
    $allowed_attachment = $this->mockAttachmentForPlan(1001, 1158);
    $blocked_attachment = $this->mockAttachmentForPlan(1002, 1158, 'Indicator');
    $allowed_attachment->expects($this->once())
      ->method('belongsToBaseObject')
      ->with($context_object)
      ->willReturn(TRUE);
    $blocked_attachment->expects($this->never())
      ->method('belongsToBaseObject');

    $object_store = new ObjectStore();
    $object_store->addObjectCollection([
      $allowed_attachment,
      $blocked_attachment,
    ], Attachment::getObjectStorageKey(), 'PlanId');

    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'objectStore', $object_store);

    $attachments = $attachment_query->getAttachmentsForPlan(1158, $context_object, [
      'AttachmentType' => 'Caseload',
    ]);

    $this->assertSame([1001], array_keys($attachments));
    $this->assertSame($allowed_attachment, reset($attachments));
  }

  /**
   * Tests that cluster context filtering preloads plan entity hierarchies.
   */
  public function testGetAttachmentsForPlanPreloadsClusterHierarchy(): void {
    $context_object = $this->createMock(GoverningEntity::class);
    $attachment = $this->mockAttachmentForPlan(1001, 1158, 'Indicator', PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY);
    $attachment->expects($this->once())
      ->method('belongsToBaseObject')
      ->with($context_object)
      ->willReturn(TRUE);

    $entity_query = $this->createMock(EntityQuery::class);
    $entity_query->expects($this->once())
      ->method('getEntitiesForPlan')
      ->with(1158);
    $queries = &drupal_static('getQueryInstance', []);
    $queries['entity'] = $entity_query;

    $object_store = new ObjectStore();
    $object_store->addObjectCollection([
      $attachment,
    ], Attachment::getObjectStorageKey(), 'PlanId');

    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'objectStore', $object_store);

    $attachments = $attachment_query->getAttachmentsForPlan(1158, $context_object);

    $this->assertSame([1001], array_keys($attachments));
  }

  /**
   * Tests that mappable data availability uses map-specific aggregate filters.
   */
  public function testMappableDataAvailabilityQueryFilters(): void {
    $fabric_client = new class() {

      /**
       * The captured query string.
       *
       * @var string
       */
      public string $query = '';

      /**
       * The captured cache tags.
       *
       * @var string[]
       */
      public array $cacheTags = [];

      /**
       * Create a Fabric query.
       */
      public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
        return new FabricQuery($query_name, $items, $filters, $limit);
      }

      /**
       * Capture and answer the Fabric query.
       */
      public function query(string $query, ?array $cache_tags = NULL): object {
        $this->query = $query;
        $this->cacheTags = $cache_tags ?? [];
        return (object) [
          'attachmentFacts' => (object) [
            'groupBy' => [],
          ],
          'measurementFacts' => (object) [
            'groupBy' => [
              (object) [
                'fields' => (object) [
                  'AttachmentId' => 1001,
                ],
                'aggregations' => (object) [
                  'count' => 1,
                ],
              ],
            ],
          ],
        ];
      }

    };
    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'fabricClient', $fabric_client);

    $availability = $attachment_query->hasMappableDataMultiple([
      1001,
      1002,
    ], [
      1001 => [2001],
      1002 => [2002],
    ]);

    $this->assertSame([
      1001 => TRUE,
      1002 => FALSE,
    ], $availability);
    $this->assertStringContainsString('attachmentFacts', $fabric_client->query);
    $this->assertStringContainsString('measurementFacts', $fabric_client->query);
    $this->assertStringContainsString('MeasurementId: { in: [2001,2002] }', $fabric_client->query);
    $this->assertStringContainsString('ValueNum: { gt: 0 }', $fabric_client->query);
    $this->assertStringContainsString('location: { AdminLevel: { gt: 0 } }', $fabric_client->query);
    $this->assertStringContainsString('GenderId: { isNull: true }', $fabric_client->query);
    $this->assertStringContainsString('AgeGroupId: { isNull: true }', $fabric_client->query);
    $this->assertStringContainsString('DeliveryModalityId: { isNull: true }', $fabric_client->query);
    $this->assertContains('attachment_id:1001', $fabric_client->cacheTags);
    $this->assertContains('attachment_id:1002', $fabric_client->cacheTags);
    $this->assertContains('measurement_id:2001', $fabric_client->cacheTags);
    $this->assertContains('measurement_id:2002', $fabric_client->cacheTags);
  }

  /**
   * Tests that map metric summaries use grouped max aggregates.
   */
  public function testMappableMapMetricSummaryQuery(): void {
    $fabric_client = new class() {

      /**
       * The captured query string.
       *
       * @var string
       */
      public string $query = '';

      /**
       * Create a Fabric query.
       */
      public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
        return new FabricQuery($query_name, $items, $filters, $limit);
      }

      /**
       * Capture and answer the Fabric query.
       */
      public function query(string $query, ?array $cache_tags = NULL): object {
        $this->query = $query;
        return (object) [
          'attachmentFacts' => (object) [
            'groupBy' => [
              (object) [
                'fields' => (object) [
                  'MetricTypeId' => 3001,
                ],
                'aggregations' => (object) [
                  'count' => 2,
                  'max' => 25,
                ],
              ],
            ],
          ],
          'measurementFacts' => (object) [
            'groupBy' => [
              (object) [
                'fields' => (object) [
                  'MeasurementId' => 2001,
                  'MetricTypeId' => 3002,
                ],
                'aggregations' => (object) [
                  'count' => 3,
                  'max' => 40,
                ],
              ],
            ],
          ],
        ];
      }

    };
    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'fabricClient', $fabric_client);

    $summary = $attachment_query->getMappableMapMetricSummary(1001, [2001]);

    $this->assertSame(25.0, $summary['base'][3001]['max']);
    $this->assertSame(40.0, $summary['measurements'][2001][3002]['max']);
    $this->assertSame(40.0, $summary['max']);
    $this->assertStringContainsString('groupBy(fields: [MetricTypeId])', $fabric_client->query);
    $this->assertStringContainsString('groupBy(fields: [MeasurementId, MetricTypeId])', $fabric_client->query);
    $this->assertStringContainsString('max(field: ValueNum)', $fabric_client->query);
    $this->assertStringContainsString('ValueNum: { gt: 0 }', $fabric_client->query);
    $this->assertStringContainsString('GenderId: { isNull: true }', $fabric_client->query);
  }

  /**
   * Tests that map metric availability uses attachment field names.
   */
  public function testMappableMapMetricAvailability(): void {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('id')->willReturn(1001);
    $attachment->method('getFields')->willReturn([
      'in_need' => 'People in need',
      'cumulative_reach' => 'Cumulative reach',
    ]);
    $measurement = $this->createMock(Measurement::class);
    $measurement->method('id')->willReturn(2001);
    $attachment->method('getMeasurements')->willReturn([$measurement]);

    $in_need = $this->createMock(MetricType::class);
    $in_need->method('getMachineName')->willReturn('in_need');
    $cumulative_reach = $this->createMock(MetricType::class);
    $cumulative_reach->method('getMachineName')->willReturn('cumulative_reach');

    $attachment_query = $this->getMockBuilder(AttachmentQuery::class)
      ->setConstructorArgs([[], 'attachment', []])
      ->onlyMethods(['getMappableMapMetricSummary', 'getMetricType'])
      ->getMock();
    $attachment_query->method('getMappableMapMetricSummary')->with(1001, [2001])->willReturn([
      'base' => [3001 => ['count' => 2, 'max' => 25]],
      'measurements' => [2001 => [3002 => ['count' => 3, 'max' => 40]]],
      'max' => 40,
      'query_succeeded' => TRUE,
    ]);
    $attachment_query->method('getMetricType')->willReturnMap([
      [3001, $in_need],
      [3002, $cumulative_reach],
    ]);

    $this->assertSame([
      'base' => ['in_need'],
      'measurements' => [2001 => ['cumulative_reach']],
    ], $attachment_query->getMappableMapMetricAvailability($attachment));
  }

  /**
   * Tests that a failed availability query does not imply missing data.
   */
  public function testMappableMapMetricAvailabilityIsUnknownAfterQueryFailure(): void {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('id')->willReturn(1001);
    $attachment->method('getMeasurements')->willReturn([]);

    $attachment_query = $this->getMockBuilder(AttachmentQuery::class)
      ->setConstructorArgs([[], 'attachment', []])
      ->onlyMethods(['getMappableMapMetricSummary'])
      ->getMock();
    $attachment_query->method('getMappableMapMetricSummary')->with(1001, [])->willReturn([
      'base' => [],
      'measurements' => [],
      'max' => 0,
      'query_succeeded' => FALSE,
    ]);

    $this->assertNull($attachment_query->getMappableMapMetricAvailability($attachment));
  }

  /**
   * Tests that map location totals use a grouped total-row slice.
   */
  public function testAttachmentMapLocationTotalsQuery(): void {
    $fabric_client = new class() {

      /**
       * The captured query string.
       *
       * @var string
       */
      public string $query = '';

      /**
       * Create a Fabric query.
       */
      public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
        return new FabricQuery($query_name, $items, $filters, $limit);
      }

      /**
       * Capture and answer the Fabric query.
       */
      public function query(string $query, ?array $cache_tags = NULL): object {
        $this->query = $query;
        return (object) [
          'attachmentFacts' => (object) [
            'groupBy' => [
              (object) [
                'fields' => (object) [
                  'LocationId' => 10,
                ],
                'aggregations' => (object) [
                  'sum' => 25,
                ],
              ],
            ],
          ],
          'measurementFacts' => (object) [
            'groupBy' => [
              (object) [
                'fields' => (object) [
                  'LocationId' => 11,
                ],
                'aggregations' => (object) [
                  'sum' => 15,
                ],
              ],
            ],
          ],
        ];
      }

    };
    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'fabricClient', $fabric_client);

    $totals = $attachment_query->getAttachmentMapLocationTotals(1001, 3001, 2001);

    $this->assertSame([
      10 => 25.0,
      11 => 15.0,
    ], $totals);
    $this->assertStringContainsString('attachmentFacts', $fabric_client->query);
    $this->assertStringContainsString('measurementFacts', $fabric_client->query);
    $this->assertStringContainsString('groupBy(fields: [LocationId])', $fabric_client->query);
    $this->assertStringContainsString('sum(field: ValueNum)', $fabric_client->query);
    $this->assertStringContainsString('AttachmentId: { eq: 1001 }', $fabric_client->query);
    $this->assertStringContainsString('MetricTypeId: { eq: 3001 }', $fabric_client->query);
    $this->assertStringContainsString('MeasurementId: { eq: 2001 }', $fabric_client->query);
    $this->assertStringContainsString('LocationId: { isNull: false }', $fabric_client->query);
    $this->assertStringContainsString('ValueNum: { gt: 0 }', $fabric_client->query);
    $this->assertStringContainsString('GenderId: { isNull: true }', $fabric_client->query);
  }

  /**
   * Tests that modal breakdowns are built from fact objects in the query layer.
   */
  public function testAttachmentMapModalBreakdown(): void {
    $fabric_client = new class() {

      /**
       * The captured query string.
       *
       * @var string
       */
      public string $query = '';

      /**
       * Create a Fabric query.
       */
      public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
        return new FabricQuery($query_name, $items, $filters, $limit);
      }

      /**
       * Capture and answer multiple Fabric queries.
       */
      public function executeMultiple(array $queries): array {
        $this->query = implode(' ', array_map(fn (FabricQuery $query) => $query->toString(), $queries));
        return [
          'attachmentFacts' => [
            AttachmentQueryTest::rawFact([
              'Id' => 1,
              'ValueNum' => 25,
            ]),
          ],
          'measurementFacts' => [
            AttachmentQueryTest::rawFact([
              'Id' => 2,
              'MeasurementId' => 2001,
              'ValueNum' => 15,
            ]),
          ],
        ];
      }

    };
    $attachment_query = new AttachmentQuery([], 'attachment', []);
    $this->setPrivateProperty($attachment_query, 'fabricClient', $fabric_client);

    $breakdown = $attachment_query->getAttachmentMapModalBreakdown(1001, 3001, 10, 2001);

    $this->assertSame(40.0, $breakdown['total']);
    $this->assertSame([], $breakdown['categories']);
    $this->assertStringContainsString('LocationId: { eq: 10 }', $fabric_client->query);
    $this->assertStringNotContainsString('ValueNum: { gt: 0 }', $fabric_client->query);
    $this->assertStringNotContainsString('GenderId: { isNull: true }', $fabric_client->query);
  }

  /**
   * Build a raw fact row.
   *
   * @param array $overrides
   *   Property overrides.
   *
   * @return object
   *   The raw fact row.
   */
  public static function rawFact(array $overrides = []): object {
    return (object) ($overrides + [
      'Id' => 1,
      'AttachmentId' => 1001,
      'MetricTypeId' => 3001,
      'CustomMetricName' => NULL,
      'LocationId' => 10,
      'GenderId' => NULL,
      'AgeGroupId' => NULL,
      'PopulationStatusId' => NULL,
      'SettlementTypeId' => NULL,
      'DisabilityStatusId' => NULL,
      'HealthInterventionCategoryId' => NULL,
      'MaternalStatusId' => NULL,
      'DisaggregationCategoryOtherId' => NULL,
      'DeliveryModalityId' => NULL,
      'IsTotal' => TRUE,
      'ValueNum' => 0,
    ]);
  }

  /**
   * Mock an attachment belonging to the given plan.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $plan_id
   *   The plan id.
   * @param string $attachment_type
   *   The attachment type.
   * @param string $source_entity_type
   *   The source entity type.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment
   *   The mocked attachment.
   */
  private function mockAttachmentForPlan(int $attachment_id, int $plan_id, string $attachment_type = 'Caseload', string $source_entity_type = PlanEntityInterface::ENTITY_TYPE_PLAN): Attachment {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id', 'getRawData', 'getSourceEntityType', 'belongsToBaseObject'])
      ->getMock();
    $attachment->method('id')->willReturn($attachment_id);
    $attachment->method('getSourceEntityType')->willReturn($source_entity_type);
    $attachment->method('getRawData')->willReturn((object) [
      'Id' => $attachment_id,
      'Name' => 'Attachment ' . $attachment_id,
      'PlanId' => $plan_id,
      'EntityId' => $plan_id,
      'EntityTypeId' => 1,
      'EntityMainType' => 'Plan',
      'AttachmentType' => $attachment_type,
    ]);
    return $attachment;
  }

}
