<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;

use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * @covers Drupal\hpc_common\Helpers\TaxonomyHelper
 */
class TaxonomyHelperTest extends UnitTestCase {

  /**
   * The taxonomy helper class.
   *
   * @var \Drupal\hpc_common\Helpers\TaxonomyHelper
   */
  protected $taxonomyHelper;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taxonomyStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity query class.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Mock term storage.
    $this->taxonomyStorage = $this->createMock(TermStorageInterface::class);

    // Mock entity type manager.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Mock entityQuery.
    $this->entityQuery = $this->prophesize(QueryInterface::class);

    // Get taxonomy tree mock.
    $tree = $this->getMockTaxonomyTree();

    // Mock loadTree.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadTree')
      ->with('test_vocabulary')
      ->willReturn($tree);

    // Set container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $this->taxonomyHelper = new TaxonomyHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    unset($this->taxonomyHelper);
    unset($this->entityTypeManager);
    unset($this->entityQuery);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for getParentTermFromChildTermName.
   */
  public function getParentTermFromChildTermNameDataProvider() {
    // Get mock parent term.
    $parent_term = $this->getMockTaxonomyTree(1);

    return [
      ['incorrect_child_name', 'test_vocabulary', FALSE],
      ['Term 2', 'test_vocabulary', $parent_term],
    ];
  }

  /**
   * Test getting parent term from a child term.
   *
   * @group TaxonomyHelper
   * @dataProvider getParentTermFromChildTermNameDataProvider
   */
  public function testGetParentTermFromChildTermName($child_term_name, $vid, $result) {
    // Get mock parent term.
    $parent_term = $this->getMockTaxonomyTree(1);

    // Mock loadByProperties.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadByProperties')
      ->with(['name' => 'Term 1'])
      ->willReturn([$parent_term]);

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->taxonomyHelper->getParentTermFromChildTermName($child_term_name, $vid));
  }

  /**
   * Data provider for loadMultipleTermsByName.
   */
  public function loadMultipleTermsByNameDataProvider() {
    $result_terms = array_slice($this->getMockTaxonomyTree(), 0, 2);

    return [
      [['Term 1', 'Term 2', 'Term 3'], 'test_vocabulary', $result_terms],
      [['Term x', 'Term y', 'Term z'], 'test_vocabulary', NULL],
    ];
  }

  /**
   * Test loading multiple terms by name.
   *
   * @group TaxonomyHelper
   * @dataProvider loadMultipleTermsByNameDataProvider
   */
  public function testLoadMultipleTermsByName($names, $vid, $result) {
    // Mock loadByProperties.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadByProperties')
      ->with(['name' => $names, 'vid' => $vid])
      ->willReturn($result);

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->taxonomyHelper->loadMultipleTermsByName($names, $vid));
  }

  /**
   * Data provider for loadMultipleTermsByVocabulary.
   */
  public function loadMultipleTermsByVocabularyDataProvider() {
    $result_terms = array_slice($this->getMockTaxonomyTree(), 0, 3);

    return [
      ['test_vocabulary', $result_terms],
      ['no_vocabulary', NULL],
    ];
  }

  /**
   * Test loading multiple terms by a vocabulary.
   *
   * @group TaxonomyHelper
   * @dataProvider loadMultipleTermsByVocabularyDataProvider
   */
  public function testLoadMultipleTermsByVocabulary($vid, $result) {
    // Mock loadByProperties.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadByProperties')
      ->with(['vid' => $vid])
      ->willReturn($result);

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->taxonomyHelper->loadMultipleTermsByVocabulary($vid));
  }

  /**
   * Data provider for getTermIdFromOriginalId.
   */
  public function getTermIdFromOriginalIdDataProvider() {
    return [
      ['125', 'test_vid', ['10', '20'], NULL],
      ['125', 'test_vid', ['10'], '10'],
      ['250', 'fail_vid', [], NULL],
    ];
  }

  /**
   * Test getting term ids from original ids.
   *
   * @group TaxonomyHelper
   * @dataProvider getTermIdFromOriginalIdDataProvider
   */
  public function testGetTermIdFromOriginalId($original_id, $vid, $query_result, $result) {
    // Mock entityQuery methods.
    $this->entityQuery->condition('field_original_id', [$original_id], 'IN')->willReturn($this->entityQuery);
    $this->entityQuery->condition('vid', $vid)->willReturn($this->entityQuery);
    $this->entityQuery->execute()->willReturn($query_result);

    // Mock getQuery.
    $this->taxonomyStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($this->entityQuery->reveal());

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->taxonomyHelper->getTermIdFromOriginalId($original_id, $vid));
  }

  /**
   * Data provider for getTermIdsByFieldValue.
   */
  public function getTermIdsByFieldValueDataProvider() {
    return [
      ['field_name', 'Jon', 'test_vocab', ['1', '2', '3'], ['1', '2', '3']],
      ['field_name', 'Snow', 'test_vocab', [], NULL],
    ];
  }

  /**
   * Test getting term ids by field values.
   *
   * @group TaxonomyHelper
   * @dataProvider getTermIdsByFieldValueDataProvider
   */
  public function testGetTermIdsByFieldValue($field_name, $value, $vid, $query_result, $result) {
    // Mock entityQuery methods.
    $this->entityQuery->condition($field_name, $value)->willReturn($this->entityQuery);
    $this->entityQuery->condition('vid', $vid)->willReturn($this->entityQuery);
    $this->entityQuery->execute()->willReturn($query_result);

    // Mock getQuery.
    $this->taxonomyStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($this->entityQuery->reveal());

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    $this->assertEquals($result, $this->taxonomyHelper->getTermIdsByFieldValue($field_name, $value, $vid));
  }

  /**
   * Get mock response for loadTree.
   */
  public function getMockTaxonomyTree($tid = NULL) {
    $terms[1] = (object) [
      'tid' => 1,
      'name' => 'Term 1',
      'parents' => [],
    ];

    $terms[2] = (object) [
      'tid' => 2,
      'name' => 'Term 2',
      'parents' => [1],
    ];

    $terms[3] = (object) [
      'tid' => 3,
      'name' => 'Term 3',
      'parents' => [],
    ];

    $terms[4] = (object) [
      'tid' => 4,
      'name' => 'Term 4',
      'parents' => [],
    ];

    $terms[5] = (object) [
      'tid' => 5,
      'name' => 'Term 5',
      'parents' => [4],
    ];

    $terms[6] = (object) [
      'tid' => 6,
      'name' => 'Term 6',
      'parents' => [4],
    ];

    return !$tid ? $terms : $terms[$tid];
  }

}
