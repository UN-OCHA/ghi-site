<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Field\FieldItemListInterface;
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
  protected function setUp(): void {
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
      ->willReturn(array_values($tree));

    // Mock loadMultiple.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadMultiple')
      ->with(array_keys($tree))
      ->willReturn($tree);

    // Set container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $this->taxonomyHelper = new TaxonomyHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
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
    // Mock term.
    $term1 = $this->prophesize(Term::class);
    $term1->getName()->willReturn('Term 1');
    // Mock term.
    $term2 = $this->prophesize(Term::class);
    $term2->getName()->willReturn('Term 2');

    return [
      [
        'Term 2',
        'test_vocabulary',
        [$term1->reveal(), $term2->reveal()],
        $term2->reveal(),
      ],
    ];
  }

  /**
   * Test you get the parent term correctly from the child term.
   *
   * @group TaxonomyHelper
   * @dataProvider getParentTermFromChildTermNameDataProvider
   */
  public function testGetParentTermFromChildTermName($child_term_name, $vid, $terms, $result) {
    // Mock loadByProperties.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadByProperties')
      ->with(['vid' => $vid])
      ->willReturn($terms);

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    // Message.
    $message = strtr('The test failed for getParentTermFromChildTermName for child term: @child and vid: @vid', [
      '@child' => $child_term_name,
      '@vid' => $vid,
    ]);

    $this->assertEquals($result, $this->taxonomyHelper->getParentTermFromChildTermName($child_term_name, $vid), $message);
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
   * Test you can load multiple taxonomy terms by name.
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

    // Message.
    $message = strtr('The test failed for loadMultipleTermsByName for names: @names and vid: @vid', [
      '@names' => print_r($names, TRUE),
      '@vid' => $vid,
    ]);

    $this->assertEquals($result, $this->taxonomyHelper->loadMultipleTermsByName($names, $vid), $message);
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
   * Test you can load multiple terms by vocabulary id.
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

    // Message.
    $message = strtr('The test failed for loadMultipleTermsByVocabulary for vid: @vid', [
      '@vid' => $vid,
    ]);

    $this->assertEquals($result, $this->taxonomyHelper->loadMultipleTermsByVocabulary($vid), $message);
  }

  /**
   * Data provider for getTermIdFromOriginalId.
   */
  public function getTermIdFromOriginalIdDataProvider() {
    $original_id = '125';

    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([['value' => $original_id]]);
    // Mock term.
    $term = $this->prophesize(Term::class);
    $term->hasField('field_original_id')->willReturn(TRUE);
    $term->get('field_original_id')->willReturn($field->reveal());
    $term->id()->willReturn('10');

    return [
      [$original_id, 'test_vid', [$term->reveal()], '10'],
      [$original_id, 'null_vid', [], NULL],
    ];
  }

  /**
   * Test getting term id from original id.
   *
   * @group TaxonomyHelper
   * @dataProvider getTermIdFromOriginalIdDataProvider
   */
  public function testGetTermIdFromOriginalId($original_id, $vid, $terms, $result) {
    // Mock loadByProperties.
    $this->taxonomyStorage->expects($this->any())
      ->method('loadByProperties')
      ->with(['vid' => $vid])
      ->willReturn($terms);

    // Get the taxonomyStorage in entityTypeManager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->taxonomyStorage);

    // Add to container.
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);

    // Message.
    $message = strtr('The test failed for getTermIdFromOriginalId for original id: @original_id and vid: @vid', [
      '@original_id' => $original_id,
      '@vid' => $vid,
    ]);

    $this->assertEquals($result, $this->taxonomyHelper->getTermIdFromOriginalId($original_id, $vid), $message);
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
   * Test getting term ids from value of a field.
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

    // Message.
    $message = strtr('The test failed for getTermIdsByFieldValue for field name: @field_name, field value: @field_value and vid: @vid', [
      '@field_name' => $field_name,
      '@field_value' => $value,
      '@vid' => $vid,
    ]);

    $this->assertEquals($result, $this->taxonomyHelper->getTermIdsByFieldValue($field_name, $value, $vid), $message);
  }

  /**
   * Get mock response for loadTree.
   */
  public function getMockTaxonomyTree() {
    $terms = [];
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

    return $terms;
  }

}
