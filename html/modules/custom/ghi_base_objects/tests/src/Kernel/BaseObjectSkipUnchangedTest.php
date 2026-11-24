<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the unchanged-aware base object migration destination.
 */
#[Group('ghi_base_objects')]
class BaseObjectSkipUnchangedTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createBaseObjectType([
      'id' => 'governing_entity',
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
   * Creates the migration under test.
   *
   * @param string $name
   *   The source name.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration.
   */
  private function createMigration(string $name): MigrationInterface {
    $definition = [
      'id' => 'base_object_skip_unchanged_test',
      'source' => [
        'plugin' => 'embedded_data',
        'track_changes' => TRUE,
        'data_rows' => [
          [
            'original_id' => 123,
            'name' => $name,
            'prototype_id' => 456,
            'icon_name' => 'test-icon',
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
        'field_original_id' => 'original_id',
        'field_prototype_id' => 'prototype_id',
        'field_icon' => 'icon_name',
      ],
      'destination' => [
        'plugin' => 'base_object_skip_unchanged',
        'overwrite_properties' => [
          'name',
          'field_prototype_id',
          'field_icon',
        ],
      ],
    ];
    return $this->container->get('plugin.manager.migration')->createStubMigration($definition);
  }

}
