<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_subpages\LogframeManager;
use Drupal\ghi_blocks\Logframe\LogframeDownloadSource;
use Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentTable;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\Plan as ApiPlan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\GoverningEntity as GoverningEntityObject;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelMultipleInterface;
use Drupal\hpc_downloads\DownloadSource\BlockSource;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests complete logframe scope, prototype worksheets and download routing.
 *
 * @group ghi_blocks
 */
class FullLogframeDownloadTest extends UnitTestCase {

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
   * Tests configured selection, worksheet labels and parent alignments.
   */
  public function testConfiguredWorkbook(): void {
    [, $entities, $context, $query_manager, $item_manager] = $this->createSourceWithLogframeData(NULL);
    $context['entities'] = [$entities[3]];
    $configuration = [
      'entities' => ['entity_ref_code' => 'ACT', 'id_type' => 'composed_reference'],
      'tables' => [
        'attachment_tables' => [
          ['item_type' => 'attachment_table', 'config' => ['attachment_prototype' => 10]],
        ],
      ],
    ];
    $plugin = $this->createMock(HPCDownloadExcelMultipleInterface::class);
    $plugin->expects($this->never())->method('buildDownloadData');
    $source = new LogframeDownloadSource($plugin, $context, $configuration, $query_manager, $item_manager, new LogframeTableConfigBuilder($this->getStringTranslationStub()));
    $this->assertTrue($source->hasConfiguredData());
    $data = $source->getData();
    $this->assertSame(['Logical framework', 'Configured table 10'], array_keys($data));
    $this->assertSame(['ACT1 description', 'CL1', 'A1', 42], $data['Configured table 10']['rows'][0]['data']);
    $this->assertSame('SO1', $data['Logical framework']['rows'][0][0]);
    $this->assertContains('ACT1', $data['Logical framework']['rows'][0]);
    $this->assertCount(1, $data['Logical framework']['rows']);
    $this->assertCount(1, $data['Configured table 10']['rows']);
  }

  /**
   * Tests all hierarchy levels, overlapping IDs and duplicate worksheet names.
   */
  public function testFullPlanWorkbook(): void {
    [$source, $entities] = $this->createSourceWithLogframeData('plan');
    $data = $source->getData();
    $this->assertCount(3, $data);
    $names = array_keys($data);
    $this->assertSame('Logical framework', $names[0]);
    $this->assertNotSame(mb_strtolower($names[1]), mb_strtolower($names[2]));
    $this->assertLessThanOrEqual(31, mb_strlen($names[1]));
    $this->assertLessThanOrEqual(31, mb_strlen($names[2]));
    foreach (array_slice($data, 1) as $sheet) {
      $references = array_column(array_column($sheet['rows'], 'data'), 1);
      $this->assertContains('HRP', $references);
      $this->assertContains('SO1', $references);
      $this->assertContains('CL1', $references);
      $this->assertContains('CL2', $references);
      $this->assertContains('ACT1', $references);
      $this->assertContains('ACT2', $references);
      $this->assertContains('ACT3', $references);
      $this->assertContains('ACT4', $references);
      $this->assertCount(count($entities) + 1, $sheet['rows']);
      foreach ($sheet['rows'] as $row) {
        $this->assertCount(count($sheet['header']), $row['data']);
      }
    }
  }

  /**
   * Tests indirect cluster descendants and alignment-only parent context.
   */
  public function testClusterWorkbook(): void {
    [$source] = $this->createSourceWithLogframeData('cluster');
    $data = $source->getData();
    foreach (array_slice($data, 1) as $sheet) {
      $references = array_column(array_column($sheet['rows'], 'data'), 1);
      $this->assertContains('CL1', $references);
      $this->assertContains('ACT1', $references);
      $this->assertContains('ACT2', $references);
      $this->assertContains('ACT4', $references);
      $this->assertNotContains('CL2', $references);
      $this->assertNotContains('ACT3', $references);
      $this->assertNotContains('SO1', $references);
      $this->assertNotContains('HRP', $references);
      foreach ($sheet['rows'] as $row) {
        $this->assertSame('CL1', $row['data'][3]);
      }
    }
    $hierarchy = array_column($data['Logical framework']['rows'], 1);
    $this->assertContains('SO1', $hierarchy);
    $this->assertContains('HRP', $hierarchy);
    $this->assertNotContains('CL2', $hierarchy);
    foreach ($data['Logical framework']['rows'] as $row) {
      $this->assertCount(7, $row);
    }
  }

  /**
   * Tests names that collide after sanitization or with reserved sheets.
   */
  public function testWorksheetNames(): void {
    $block = (new \ReflectionClass(LogframeDownloadSource::class))->newInstanceWithoutConstructor();
    $block->setStringTranslation($this->getStringTranslationStub());
    $arguments = ['Meta data', ['meta data']];
    $this->assertSame('Meta data (2)', $this->callPrivateMethod($block, 'getFullLogframeSheetName', $arguments));
    $this->assertSame('A B (2)', $this->callPrivateMethod($block, 'getFullLogframeSheetName', ['A/B', ['a b']]));
    $this->assertSame('Data', $this->callPrivateMethod($block, 'getFullLogframeSheetName', ['', []]));
  }

  /**
   * Tests that download scope is preserved and regular exports stay separate.
   */
  public function testDownloadSource(): void {
    [, , $context, $query_manager, $item_manager] = $this->createSourceWithLogframeData('plan');
    $block = $this->getMockBuilder(PlanEntityLogframe::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getCurrentUri', 'getUuid', 'label', 'getDownloadCaption',
        'getBlockContext', 'getBlockConfig', 'getCurrentPlanObject',
        'getCurrentBaseObject', 'getCurrentSectionNode', 'getPageNode',
      ])
      ->getMock();
    $block->setStringTranslation($this->getStringTranslationStub());
    $block->method('getCurrentUri')->willReturn('/node/1');
    $block->method('getUuid')->willReturn('block-uuid');
    $block->method('label')->willReturn('Strategic Objectives');
    $block->method('getDownloadCaption')->willReturn('Plan');
    $block->method('getCurrentPlanObject')->willReturn($context['plan_object']);
    $block->method('getCurrentBaseObject')->willReturn($context['base_object']);
    $block->method('getBlockContext')->willReturn($context + ['entities' => []]);
    $block->method('getBlockConfig')->willReturn(['entities' => [], 'tables' => []]);
    $manager = $this->createMock(LogframeManager::class);
    $manager->method('getEntityTypesFromPlanObject')->willReturn([]);
    $block->logframeManager = $manager;
    $this->setPrivateProperty($block, 'fabricQueryManager', $query_manager);
    $this->setPrivateProperty($block, 'configurationContainerItemManager', $item_manager);
    $this->setPrivateProperty($block, 'tableConfigBuilder', new LogframeTableConfigBuilder($this->getStringTranslationStub()));
    $stack = new RequestStack();
    $request = new Request(['logframe_scope' => 'plan']);
    $stack->push($request);
    $this->setPrivateProperty($block, 'requestStack', $stack);

    // Query parameters outside download routes must not change block scope.
    $source = $block->getDownloadSource();
    $this->assertArrayNotHasKey('logframe_scope', $source->getDialogOptions());
    $this->assertNull($source->getData());
    $this->assertSame((new BlockSource($block))->getDownloadFileName('xlsx'), $source->getDownloadFileName('xlsx'));
    foreach (['hpc_downloads.download_dialog', 'hpc_downloads.initiate'] as $route) {
      $request->attributes->set('_route', $route);
      $source = $block->getDownloadSource();
      $this->assertInstanceOf(LogframeDownloadSource::class, $source);
      $this->assertSame('plan', $source->getDialogOptions()['logframe_scope']);
      $this->assertCount(3, $source->getData());
      $this->assertNotSame($source->getDownloadFileName('xlsx'), (new BlockSource($block))->getDownloadFileName('xlsx'));
      // The compatibility entry point always exports the configured selection.
      $this->assertNull($block->buildDownloadData());
    }

    $request->query->remove('logframe_scope');
    $this->assertNull($block->getDownloadSource()->getData());
    $request->query->set('logframe_scope', 'unsupported');
    $this->expectException(NotFoundHttpException::class);
    $block->getDownloadSource();
  }

  /**
   * Tests that a cluster scope cannot be requested without cluster context.
   */
  public function testUnavailableClusterScope(): void {
    [, , $context, $query_manager, $item_manager] = $this->createSourceWithLogframeData('plan');
    unset($context['base_object']);
    $this->expectException(NotFoundHttpException::class);
    new LogframeDownloadSource($this->createMock(HPCDownloadExcelMultipleInterface::class), $context, [], $query_manager, $item_manager, new LogframeTableConfigBuilder($this->getStringTranslationStub()), 'cluster');
  }

  /**
   * Creates a download source with multiple levels and two clusters.
   */
  private function createSourceWithLogframeData(?string $scope): array {
    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn(1);
    $plan->method('getPlanLanguage')->willReturn('en');
    $plan->method('getPlanClusterType')->willReturn(Plan::CLUSTER_TYPE_CLUSTER);
    $cluster_object = $this->createMock(GoverningEntityObject::class);
    $cluster_object->method('getSourceId')->willReturn(1);
    $api_plan = $this->mockEntity(ApiPlan::class, 1, 'PL', 'HRP');
    $cluster = $this->mockEntity(GoverningEntity::class, 1, 'CL', 'CL1');
    $other_cluster = $this->mockEntity(GoverningEntity::class, 2, 'CL', 'CL2');
    $objective = $this->mockEntity(PlanEntity::class, 1, 'SO', 'SO1');
    $activity = $this->mockEntity(PlanEntity::class, 2, 'ACT', 'ACT1', $cluster, [$objective]);
    $child = $this->mockEntity(PlanEntity::class, 3, 'ACT', 'ACT2', NULL, [$activity]);
    $grandchild = $this->mockEntity(PlanEntity::class, 5, 'ACT', 'ACT4', NULL, [$child]);
    // A cross-cluster alignment must not override a direct cluster assignment.
    $other_activity = $this->mockEntity(PlanEntity::class, 4, 'ACT', 'ACT3', $other_cluster, [$activity]);
    $entities = [$grandchild, $child, $objective, $activity, $other_activity, $cluster, $other_cluster];

    $entity_query = $this->createMock(EntityQuery::class);
    $entity_query->method('getEntitiesForPlan')->willReturnCallback(function ($plan_id, $context, $type) use ($entities) {
      return array_filter($entities, fn ($entity) => $type == 'governing' ? $entity instanceof GoverningEntity : $entity instanceof PlanEntity);
    });
    $plan_query = $this->createMock(PlanQuery::class);
    $plan_query->method('getPlan')->with(1)->willReturn($api_plan);
    $prototypes = [];
    foreach ([10, 11] as $id) {
      $prototype = $this->createMock(AttachmentPrototype::class);
      $prototype->method('id')->willReturn($id);
      $prototype->method('getName')->willReturn('A very long shared attachment prototype name ' . $id);
      $prototype->method('getEntityRefCodes')->willReturn(['PL', 'SO', 'CL', 'ACT']);
      $prototype->method('getFieldTypes')->willReturn([]);
      $prototype->method('getMeasurementFields')->willReturn([]);
      $prototypes[$id] = $prototype;
    }
    $prototype_query = $this->createMock(AttachmentPrototypeQuery::class);
    $prototype_query->method('getDataPrototypesForPlan')->willReturn($prototypes);
    $queries = [
      'entity' => $entity_query,
      'entity_prototype' => $this->createMock(EntityPrototypeQuery::class),
      'plan' => $plan_query,
      'attachment_prototype' => $prototype_query,
    ];
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $attachment_query->method('getAttachmentsForEntities')->willReturn([]);
    $queries['attachment'] = $attachment_query;
    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->method('createInstance')->willReturnCallback(fn ($name) => $queries[$name]);
    $item_manager = $this->createMock(ConfigurationContainerItemManager::class);
    $item_manager->method('createInstance')->willReturnCallback(function () {
      $table = $this->getMockBuilder(AttachmentTable::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['getRenderArray'])
        ->getMock();
      $table->method('getRenderArray')->willReturnCallback(fn () => [
        '#prototype_id' => $table->get('attachment_prototype'),
        '#download_label' => 'Configured table ' . $table->get('attachment_prototype'),
        '#header' => [['data' => 'Value', 'data-column-type' => 'amount']],
        '#rows' => [['data' => [42], 'data-attachment-custom-id' => 'A1']],
      ]);
      return $table;
    });
    $context = [
      'plan_object' => $plan,
      'base_object' => $cluster_object,
      'attachment_prototypes' => $prototypes,
    ];
    // A generic download plugin proves workbook generation needs no block API.
    $plugin = $this->createMock(HPCDownloadExcelMultipleInterface::class);
    $plugin->expects($this->never())->method('buildDownloadData');
    $source = new LogframeDownloadSource($plugin, $context, [], $query_manager, $item_manager, new LogframeTableConfigBuilder($this->getStringTranslationStub()), $scope);
    $source->setStringTranslation($this->getStringTranslationStub());
    return [$source, $entities, $context, $query_manager, $item_manager];
  }

  /**
   * Mocks an API entity with its reference, source type and relationships.
   */
  private function mockEntity(string $class, int $id, string $ref_code, string $reference, ?GoverningEntity $cluster = NULL, array $parents = []): PlanEntityInterface {
    $entity = $this->createMock($class);
    $entity->method('id')->willReturn($id);
    $entity->method('getEntityTypeRefCode')->willReturn($ref_code);
    $entity->method('getDescription')->willReturn($reference . ' description');
    $entity->method('getCustomName')->willReturn($reference);
    if ($entity instanceof PlanEntity) {
      $entity->method('getEntityType')->willReturn(PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY);
      $entity->method('getParentGoverningEntity')->willReturn($cluster);
      $parent_ids = array_map(fn ($parent) => $parent->id(), $parents);
      $entity->method('getPlanEntityParents')->willReturn($parents ? array_combine($parent_ids, $parents) : []);
    }
    elseif ($entity instanceof GoverningEntity) {
      $entity->method('getEntityType')->willReturn(PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY);
      $entity->method('getDisplayName')->willReturn($reference);
    }
    else {
      $entity->method('getEntityType')->willReturn(PlanEntityInterface::ENTITY_TYPE_PLAN);
      $entity->method('getPlanTypeAbbreviation')->willReturn('HRP');
    }
    return $entity;
  }

}
