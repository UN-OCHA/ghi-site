<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the unchanged-aware base object migration destination.
 */
#[Group('ghi_base_objects')]
class BaseObjectSkipUnchangedTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options', 'ghi_plans'];

  /**
   * The import request time, advanced between runs.
   *
   * @var int
   */
  private int $requestTime = 1800000000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn(): int => $this->requestTime);
    $this->container->set('datetime.time', $time);

    $this->createBaseObjectType(['id' => 'plan']);
    $this->createBaseObject(['type' => 'plan', 'field_original_id' => 99]);
    $this->createBaseObjectType([
      'id' => 'governing_entity',
      'field_plan' => 'Plan',
      'field_prototype_id' => [
        'type' => 'integer',
        'label' => 'Prototype ID',
      ],
      'field_icon' => [
        'type' => 'string',
        'label' => 'Icon',
      ],
    ]);
  }

  /**
   * Tests that forced imports save only new or changed base objects.
   */
  public function testForcedImportSkipsUnchangedBaseObject(): void {
    $migration = $this->createMigration('Initial name');
    $result = (new MigrateExecutable($migration))->import();
    $messages = iterator_to_array($migration->getIdMap()->getMessages());
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $result, print_r($messages, TRUE));

    $map_row = $migration->getIdMap()->getRowBySource(['original_id' => 123]);
    $destination_id = $map_row['destid1'];
    $storage = $this->container->get('entity_type.manager')->getStorage('base_object');
    $this->assertSame('Initial name', $storage->loadUnchanged($destination_id)->label());

    \Drupal::state()->set('ghi_base_objects_test.base_object_update_count', 0);
    $migration = $this->createMigration('Initial name');
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $this->assertSame(0, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $map_row = $migration->getIdMap()->getRowBySource(['original_id' => 123]);
    $this->assertSame(MigrateIdMapInterface::STATUS_IMPORTED, (int) $map_row['source_row_status']);
    $this->assertSame($destination_id, $map_row['destid1']);

    $migration = $this->createMigration('Updated name');
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $this->assertSame(1, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $this->assertSame('Updated name', $storage->loadUnchanged($destination_id)->label());

    $storage->loadUnchanged($destination_id)->delete();
    $migration = $this->createMigration('Updated name');
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $map_row = $migration->getIdMap()->getRowBySource(['original_id' => 123]);
    $this->assertNotSame($destination_id, $map_row['destid1']);
    $this->assertSame('Updated name', $storage->loadUnchanged($map_row['destid1'])->label());
  }

  /**
   * Tests source timestamps and import-time fallback across forced imports.
   */
  #[DataProvider('timestampProvider')]
  public function testTimestampFallbacks(?string $updated_at, ?string $created_at, ?int $expected_changed): void {
    $timestamps = ['updated_at' => $updated_at, 'created_at' => $created_at];
    $migration = $this->createMigration('Initial name', $timestamps);
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $map_row = $migration->getIdMap()->getRowBySource(['original_id' => 123]);
    $destination_id = $map_row['destid1'];
    $storage = $this->container->get('entity_type.manager')->getStorage('base_object');
    $initial_changed = $expected_changed ?? $this->requestTime;
    $this->assertSame($initial_changed, $storage->loadUnchanged($destination_id)->getChangedTime());

    $this->requestTime += 3600;
    \Drupal::state()->set('ghi_base_objects_test.base_object_update_count', 0);
    $migration = $this->createMigration('Initial name', $timestamps);
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $this->assertSame(0, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $this->assertSame($initial_changed, $storage->loadUnchanged($destination_id)->getChangedTime());

    $this->requestTime += 3600;
    $migration = $this->createMigration('Updated name', $timestamps);
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $this->assertSame(1, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $entity = $storage->loadUnchanged($destination_id);
    $this->assertSame('Updated name', $entity->label());
    $this->assertSame($expected_changed ?? $this->requestTime, $entity->getChangedTime());
  }

  /**
   * Tests importing a changed source timestamp without any other data changes.
   */
  public function testUpdatedTimestampIsImported(): void {
    $migration = $this->createMigration('Initial name', ['created_at' => '2026-06-02T07:41:38Z']);
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $map_row = $migration->getIdMap()->getRowBySource(['original_id' => 123]);

    \Drupal::state()->set('ghi_base_objects_test.base_object_update_count', 0);
    $migration = $this->createMigration('Initial name', [
      'created_at' => '2026-06-02T07:41:38Z',
      'updated_at' => '2026-08-06T11:09:43Z',
    ]);
    $migration->getIdMap()->prepareUpdate();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration))->import());
    $this->assertSame(1, \Drupal::state()->get('ghi_base_objects_test.base_object_update_count'));
    $entity = $this->container->get('entity_type.manager')->getStorage('base_object')->loadUnchanged($map_row['destid1']);
    $this->assertSame(1786014583, $entity->getChangedTime());
  }

  /**
   * Provides source timestamps and the expected source-derived changed time.
   *
   * @return array
   *   Updated date, created date, and expected timestamp or import fallback.
   */
  public static function timestampProvider(): array {
    return [
      'updated date' => ['2026-08-06T11:09:43Z', '2026-06-02T07:41:38Z', 1786014583],
      'created date fallback' => [NULL, '2026-06-02T07:41:38Z', 1780386098],
      'empty updated date fallback' => ['', '2026-06-02T07:41:38Z', 1780386098],
      'updated date without created date' => ['2026-08-06T11:09:43Z', NULL, 1786014583],
      'import time fallback' => [NULL, NULL, NULL],
    ];
  }

  /**
   * Creates the migration under test.
   *
   * @param string $name
   *   The source name.
   * @param array $timestamps
   *   Optional source timestamps keyed by updated_at and created_at.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration.
   */
  private function createMigration(string $name, array $timestamps = []): MigrationInterface {
    $definition = [
      'id' => 'governing_entity',
      'source' => [
        'plugin' => 'embedded_data',
        'track_changes' => TRUE,
        'data_rows' => [
          [
            'original_id' => 123,
            'name' => $name,
            'prototype_id' => 456,
            'icon_name' => 'test-icon',
            'raw' => [
              'Name' => $name,
              'PlanId' => 99,
              'UpdatedAt' => $timestamps['updated_at'] ?? NULL,
              'CreatedAt' => $timestamps['created_at'] ?? NULL,
            ],
          ],
        ],
        'ids' => [
          'original_id' => ['type' => 'integer'],
        ],
      ],
      'process' => [
        'type' => [
          'plugin' => 'default_value',
          'default_value' => 'governing_entity',
        ],
        'name' => 'name',
        'changed' => 'changed',
        'field_original_id' => 'original_id',
        'field_plan' => 'plan_entity_id',
        'field_prototype_id' => 'prototype_id',
        'field_icon' => 'icon_name',
      ],
      'destination' => [
        'plugin' => 'base_object_skip_unchanged',
        'overwrite_properties' => [
          'name',
          'changed',
          'field_prototype_id',
          'field_icon',
        ],
      ],
    ];
    return $this->container->get('plugin.manager.migration')->createStubMigration($definition);
  }

}
