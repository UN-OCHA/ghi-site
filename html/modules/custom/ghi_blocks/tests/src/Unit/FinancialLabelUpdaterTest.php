<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_blocks\Services\FinancialLabelUpdater;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\FinancialLabel;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests updates to financial table label overrides.
 */
#[CoversClass(FinancialLabelUpdater::class)]
#[Group('ghi_blocks')]
class FinancialLabelUpdaterTest extends UnitTestCase {

  /**
   * Tests container construction.
   */
  public function testCreate(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $translation = $this->createMock(TranslationInterface::class);
    $database = $this->createMock(Connection::class);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      ['entity_type.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $entity_type_manager],
      ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $translation],
      ['database', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $database],
    ]);

    $this->assertInstanceOf(FinancialLabelUpdater::class, FinancialLabelUpdater::create($container));
  }

  /**
   * Tests selection of current entities containing financial items.
   */
  public function testGetEntityReferences(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(TRUE);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('hasDefinition')->willReturn(TRUE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('escapeLike')->willReturnArgument(0);

    $queries = [
      'node__layout_builder__layout' => $this->mockEntityReferenceQuery('node__layout_builder__layout', [12, 34]),
      'page_template__layout_builder__layout' => $this->mockEntityReferenceQuery('page_template__layout_builder__layout', [56]),
    ];
    $database->method('select')->willReturnCallback(fn (string $table) => $queries[$table]);

    $updater = new FinancialLabelUpdater($entity_type_manager, $this->createMock(TranslationInterface::class), $database);
    $this->assertSame([
      [
        'entity_type_id' => 'node',
        'entity_id' => 12,
      ],
      [
        'entity_type_id' => 'node',
        'entity_id' => 34,
      ],
      [
        'entity_type_id' => 'page_template',
        'entity_id' => 56,
      ],
    ], $updater->getEntityReferences());
  }

  /**
   * Tests batched updates, revision handling, and result totals.
   */
  public function testUpdateEntityReferences(): void {
    $component = new SectionComponent('component', 'content', [
      'id' => 'plan_organizations_table',
      'table' => [
        'columns' => [
          $this->column('project_funding', 'current_requirements', 'Requirements ($)'),
        ],
      ],
    ]);
    $section = new Section('layout_onecol', [], [$component]);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['section' => $section]]);
    $field->expects($this->once())->method('setValue');

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('isRevisionable')->willReturn(TRUE);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasField')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn(TRUE);
    $entity->method('get')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn($field);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->expects($this->once())->method('setNewRevision')->with(FALSE);
    $entity->expects($this->once())->method('setSyncing')->with(TRUE);
    $entity->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with([12, 34])
      ->willReturn([12 => $entity]);
    $storage->expects($this->once())
      ->method('resetCache')
      ->with([12, 34]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $progress_calls = 0;
    $updater = new FinancialLabelUpdater($entity_type_manager, $this->createMock(TranslationInterface::class), $this->createMock(Connection::class));
    $results = $updater->updateEntityReferences([
      [
        'entity_type_id' => 'node',
        'entity_id' => 12,
      ],
      [
        'entity_type_id' => 'node',
        'entity_id' => 34,
      ],
    ], function () use (&$progress_calls): void {
      $progress_calls++;
    });

    $this->assertSame([
      'processed' => 2,
      'updated_entities' => 1,
      'removed' => 0,
      'stripped' => 1,
      'missing' => 1,
    ], $results);
    $this->assertSame(2, $progress_calls);
    $this->assertSame('Requirements', $component->get('configuration')['table']['columns'][0]['config']['label']);
  }

  /**
   * Tests cleanup across item types, block plugins, and configuration paths.
   */
  public function testUpdateEntity(): void {
    $plan = $this->getMockBuilder(Plan::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanLanguage'])
      ->getMock();
    $plan->method('getPlanLanguage')->willReturn('fr');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'type' => Plan::BUNDLE,
        'field_original_id' => 123,
      ])
      ->willReturn([$plan]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('base_object')
      ->willReturn($storage);

    $translation_calls = [];
    $translations = [
      'Funding' => 'Financement',
      'Original requirements' => 'Besoins financiers initiaux',
      'Requirements' => 'Besoins financiers',
    ];
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(function ($string, array $args = [], array $options = []) use (&$translation_calls, $translations) {
        $translation_calls[] = [$string, $options];
        return $translations[(string) $string] ?? $string;
      });

    $hpc_columns = [
      $this->column('project_funding', 'current_requirements', "  BESOINS\tFINANCIERS ($)  "),
      $this->column('project_funding', 'current_requirements', 'Financial needs ($)'),
      $this->column('project_funding', 'current_requirements', 'Besoins financiers ($) annually'),
      $this->column('project_funding', 'current_requirements', 'Requirements ($)'),
      $this->column('project_funding', 'original_requirements', 'Besoins financiers initiaux ($)'),
      $this->column('funding_data', 'current_requirements', 'Besoins financiers ($)'),
      $this->column('funding_data', 'funding_totals', 'Financement ($)'),
      $this->column('funding_data', 'funding_totals', 'Received funding ($)'),
      $this->column('funding_data', 'original_requirements', 'Besoins financiers ($)'),
      $this->column('project_funding', 'coverage', 'Coverage ($)'),
    ];
    $hpc_component = new SectionComponent('hpc-component', 'content', [
      'id' => 'an_arbitrary_table_plugin',
      'context_mapping' => ['plan' => 'plan--123'],
      'hpc' => ['table' => ['columns' => $hpc_columns]],
    ]);
    $table_columns = [
      $this->column('project_funding', 'requirements_changes', 'Requirements changes ($)'),
      $this->column('funding_data', 'outside_funding', 'Funded outside HRP ($)'),
      $this->column('funding_data', 'funding_gap', 'Shortfall ($)'),
    ];
    $table_component = new SectionComponent('table-component', 'content', [
      'id' => 'another_table_plugin',
      'contexts' => ['context_mapping' => ['plan' => 'plan--123']],
      'table' => ['columns' => $table_columns],
    ]);
    $section = new Section('layout_onecol', [], [$hpc_component, $table_component]);

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['section' => $section]]);
    $field->expects($this->once())->method('setValue');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasField')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn(TRUE);
    $entity->method('get')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn($field);

    $updater = new FinancialLabelUpdater($entity_type_manager, $translation, $this->createMock(Connection::class));
    $this->assertSame([
      'removed' => 6,
      'stripped' => 5,
    ], $updater->updateEntity($entity));
    $this->assertContains([
      'Requirements',
      [
        'langcode' => 'fr',
        'context' => FinancialLabel::TRANSLATION_CONTEXT,
      ],
    ], $translation_calls);
    $this->assertContains(['Funding', ['langcode' => 'fr']], $translation_calls);

    $migrated_hpc_columns = $hpc_component->get('configuration')['hpc']['table']['columns'];
    $this->assertArrayNotHasKey('label', $migrated_hpc_columns[0]['config']);
    $this->assertSame('Financial needs', $migrated_hpc_columns[1]['config']['label']);
    $this->assertSame('Besoins financiers ($) annually', $migrated_hpc_columns[2]['config']['label']);
    $this->assertSame('Requirements', $migrated_hpc_columns[3]['config']['label']);
    $this->assertArrayNotHasKey('label', $migrated_hpc_columns[4]['config']);
    $this->assertArrayNotHasKey('label', $migrated_hpc_columns[5]['config']);
    $this->assertArrayNotHasKey('label', $migrated_hpc_columns[6]['config']);
    $this->assertSame('Received funding', $migrated_hpc_columns[7]['config']['label']);
    $this->assertSame('Besoins financiers', $migrated_hpc_columns[8]['config']['label']);
    $this->assertSame('Coverage ($)', $migrated_hpc_columns[9]['config']['label']);

    $migrated_table_columns = $table_component->get('configuration')['table']['columns'];
    $this->assertArrayNotHasKey('label', $migrated_table_columns[0]['config']);
    $this->assertArrayNotHasKey('label', $migrated_table_columns[1]['config']);
    $this->assertSame('Shortfall', $migrated_table_columns[2]['config']['label']);
  }

  /**
   * Tests that custom units are stripped without a mapped plan.
   */
  public function testMissingPlanMappingStillStripsCurrencyUnit(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->never())->method('getStorage');
    $translation = $this->createMock(TranslationInterface::class);
    $translation->expects($this->never())->method('translate');
    $component = new SectionComponent('component', 'content', [
      'id' => 'plan_organizations_table',
      'table' => [
        'columns' => [
          $this->column('project_funding', 'current_requirements', 'Requirements ($)'),
        ],
      ],
    ]);

    $updater = new FinancialLabelUpdater($entity_type_manager, $translation, $this->createMock(Connection::class));
    $this->assertSame([
      'removed' => 0,
      'stripped' => 1,
    ], $updater->updateSection(new Section('layout_onecol', [], [$component])));
    $this->assertSame('Requirements', $component->get('configuration')['table']['columns'][0]['config']['label']);
  }

  /**
   * Builds a configured table column.
   */
  private function column(string $item_type, string $data_type, string $label): array {
    return [
      'item_type' => $item_type,
      'config' => [
        'data_type' => $data_type,
        'label' => $label,
      ],
    ];
  }

  /**
   * Mocks a current-layout entity-reference query.
   */
  private function mockEntityReferenceQuery(string $table, array $entity_ids): SelectInterface {
    $condition = $this->createMock(ConditionInterface::class);
    $condition->method('condition')->willReturnSelf();
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')
      ->with(FetchAs::Column)
      ->willReturn($entity_ids);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->with($table, ['entity_id'])->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($condition);
    $query->method('condition')->with($condition)->willReturnSelf();
    $query->method('orderBy')->with('entity_id')->willReturnSelf();
    $query->method('distinct')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    return $query;
  }

}
