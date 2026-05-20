<?php

namespace Drupal\Tests\ghi_content\Unit\Import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_content\Import\TargetedMigrationImporter;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\Log\NullLogger;

/**
 * Tests targeted migration imports.
 *
 * @group ghi_content
 */
class TargetedMigrationImporterTest extends UnitTestCase {

  /**
   * Tests that targeted update imports prepare the selected source id.
   */
  public function testTargetedImportMarksSelectedSourceIdForUpdate(): void {
    $id_map = $this->createMock(MigrateIdMapInterface::class);
    $id_map->expects($this->once())
      ->method('setUpdate')
      ->with(['id' => '123']);

    $source = $this->createMock(MigrateSourceInterface::class);
    $source->expects($this->once())
      ->method('getIds')
      ->willReturn(['id' => ['type' => 'integer']]);

    $migration = $this->createMock(MigrationInterface::class);
    $migration->expects($this->once())->method('getSourcePlugin')->willReturn($source);
    $migration->method('getIdMap')->willReturn($id_map);
    // Keep the executable from doing a full import. The important part for this
    // test is that the importer mirrors migrate_tools' update preparation
    // before control passes to the executable.
    $migration->method('getStatus')->willReturn(MigrationInterface::STATUS_IMPORTING);
    $migration->method('id')->willReturn('articles_hpc_content_module');
    $migration->method('getStatusLabel')->willReturn('Importing');

    $container = new ContainerBuilder();
    $string_translation = $this->createMock(TranslationInterface::class);
    $string_translation->method('translateString')->willReturnCallback(fn($string) => $string);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new NullLogger());
    $container->set('event_dispatcher', new EventDispatcher());
    $container->set('logger.factory', $logger_factory);
    $container->set('string_translation', $string_translation);
    \Drupal::setContainer($container);

    $importer = new TargetedMigrationImporter(
      $this->createMock(KeyValueFactoryInterface::class),
      $this->createMock(TimeInterface::class),
      $string_translation,
    );

    $error_reporting = error_reporting(E_ALL & ~E_DEPRECATED);
    try {
      $this->assertFalse($importer->import($migration, 123));
    }
    finally {
      error_reporting($error_reporting);
    }
  }

}
