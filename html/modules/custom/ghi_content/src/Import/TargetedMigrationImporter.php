<?php

namespace Drupal\ghi_content\Import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Runs targeted migration imports for individual remote content items.
 */
class TargetedMigrationImporter {

  /**
   * The key-value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a targeted migration importer.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value store factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, TimeInterface $time, TranslationInterface $string_translation) {
    $this->keyValueFactory = $key_value_factory;
    $this->time = $time;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Run a targeted import for one remote item.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration to run.
   * @param int $remote_id
   *   The remote content id.
   *
   * @return bool
   *   TRUE if the migration did not fail, FALSE otherwise.
   */
  public function import(MigrationInterface $migration, int $remote_id): bool {
    $options = [
      'idlist' => (string) $remote_id,
      'update' => TRUE,
    ];

    // This mirrors migrate_tools' --update --idlist preparation. Passing an
    // idlist to the executable only filters the source rows; the corresponding
    // id map row still needs to be marked as updateable when the item has been
    // imported before.
    $source_id_keys = array_keys($migration->getSourcePlugin()->getIds());
    $migration->getIdMap()->setUpdate(array_combine($source_id_keys, [(string) $remote_id]));

    // Use the existing migration for creation instead of manually creating a
    // node here. That keeps the source-to-destination id map in sync, so the
    // next scheduled migration updates this node instead of importing a
    // duplicate for the same remote item.
    $executable = new MigrateExecutable($migration, new MigrateMessage(), $this->keyValueFactory, $this->time, $this->stringTranslation, $options);
    return $executable->import() !== MigrationInterface::RESULT_FAILED;
  }

}
