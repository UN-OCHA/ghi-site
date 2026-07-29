<?php

namespace Drupal\Tests\ghi_plans\Unit\Plugin\FabricQuery;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\ObjectStore;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the attachment prototype Fabric query plugin.
 *
 * @group ghi_plans
 */
class AttachmentPrototypeQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    drupal_static_reset();
  }

  /**
   * Tests that retrieved prototypes apply configured field replacements.
   */
  public function testRetrievedPrototypeAppliesConfiguredFieldReplacement(): void {
    $fabric_client = new class(self::rawStalePrototype()) {

      /**
       * The raw prototype row returned by Fabric.
       *
       * @var object
       */
      private object $prototype;

      /**
       * Captured item query strings.
       *
       * @var string[]
       */
      public array $executeQueries = [];

      /**
       * Captured grouped fact query string.
       *
       * @var string
       */
      public string $groupQuery = '';

      /**
       * Constructs the fake Fabric client.
       */
      public function __construct(object $prototype) {
        $this->prototype = $prototype;
      }

      /**
       * Create a Fabric query.
       */
      public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
        return new class($query_name, $items, $filters, $limit, $this) extends FabricQuery {

          /**
           * The fake Fabric client that owns this query.
           *
           * @var object
           */
          private object $client;

          /**
           * Constructs the fake Fabric query.
           */
          public function __construct(?string $query_name, mixed $items, ?array $filters, ?int $limit, object $client) {
            parent::__construct($query_name, $items, $filters, $limit);
            $this->client = $client;
          }

          /**
           * Execute the fake query through the owning fake client.
           */
          public function execute(string $key_property = 'Id'): false|array|object {
            return $this->client->execute($this, $key_property);
          }

        };
      }

      /**
       * Execute an item query.
       */
      public function execute(FabricQuery $query, string $key_property = 'Id'): false|array|object {
        $this->executeQueries[] = $query->toString();
        return match ($query->getQueryName()) {
          'attachmentPrototypes' => [
            5647 => $this->prototype,
          ],
          default => [],
        };
      }

      /**
       * Execute a grouped fact query.
       */
      public function query(string $query, array $cache_tags = []): object {
        $this->groupQuery = $query;
        return (object) [];
      }

    };

    $key_value_store = $this->prophesize(KeyValueStoreInterface::class);
    $key_value_store->get(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY, [])->willReturn([
      'replacements' => [
        5647 => [
          2 => [
            'metric_type' => 'cumulative_reach',
            'field_group' => AttachmentPrototype::FIELD_GROUP_MEASUREMENT,
          ],
        ],
      ],
    ]);
    $key_value_factory = $this->prophesize(KeyValueFactoryInterface::class);
    $key_value_factory->get(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)->willReturn($key_value_store->reveal());

    $query = new AttachmentPrototypeQuery([], 'attachment_prototype', []);
    $this->setPrivateProperty($query, 'fabricClient', $fabric_client);
    $this->setPrivateProperty($query, 'objectStore', new ObjectStore());
    $this->setPrivateProperty($query, 'keyValueFactory', $key_value_factory->reveal());
    $this->setPrivateProperty($query, 'baseTypes', [
      'metricTypes' => [
        14 => new MetricType((object) [
          'Id' => 14,
          'Name' => 'Cumulative reach',
          'HPCType' => 'cumulativeReach',
          'LabelLookup' => 'Cumulative reach',
        ]),
      ],
    ]);

    $prototype = $query->getPrototype(5647);

    $this->assertInstanceOf(AttachmentPrototype::class, $prototype);
    $this->assertSame([
      'periodical_reach' => 'People reached (periodical)',
      'cumulative_reach' => 'People covered',
    ], $prototype->getMeasurementFields());
    $this->assertSame(2, $prototype->getOriginalIndexByMetricType('cumulative_reach'));
    $this->assertSame('cumulative_reach', $prototype->getMetricTypeByOriginalIndex(2));
    $this->assertSame('', $fabric_client->groupQuery);
  }

  /**
   * Tests that normalized field overrides keep configured addition labels.
   */
  public function testNormalizeFieldOverridesKeepsAdditionLabels(): void {
    $overrides = AttachmentPrototypeQuery::normalizeFieldOverrides([
      'additions' => [
        9999 => [
          'metric_type' => 'custom',
          'field_group' => AttachmentPrototype::FIELD_GROUP_MEASUREMENT,
          'label' => 'Reached Cumulative Covid',
        ],
      ],
    ]);

    $this->assertSame([
      'metric_type' => 'custom',
      'field_group' => AttachmentPrototype::FIELD_GROUP_MEASUREMENT,
      'label' => 'Reached Cumulative Covid',
    ], $overrides[9999]['additions'][0]);
  }

  /**
   * Build a stale raw prototype row.
   *
   * @return object
   *   A raw attachment prototype row.
   */
  private static function rawStalePrototype(): object {
    return (object) [
      'Id' => 5647,
      'RefCode' => 'BP',
      'Type' => 'caseLoad',
      'Value' => (object) [
        'measureFields' => [
          (object) [
            'name' => (object) ['en' => 'People reached (periodical)'],
            'type' => 'periodicalReach',
          ],
          (object) [
            'name' => (object) ['en' => 'People covered'],
            'type' => 'covered',
          ],
        ],
        'metrics' => [
          (object) [
            'name' => (object) ['en' => 'People targeted'],
            'type' => 'target',
          ],
        ],
        'name' => (object) ['en' => 'Caseload'],
        'entities' => [],
      ],
      'PlanId' => 1143,
      'CreatedAt' => '2022-09-28T10:09:09.000Z',
      'UpdatedAt' => '2024-09-13T17:07:39.000Z',
    ];
  }

}
