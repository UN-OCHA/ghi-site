<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * @covers Drupal\hpc_common\Helpers\TaxonomyHelper
 */
class TaxonomyHelperTest extends UnitTestCase {

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
    $tree = self::createTaxonomyTree();

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
  public static function getParentTermFromChildTermNameDataProvider() {
    return [
      [
        'Term 2',
        'test_vocabulary',
        ['Term 1', 'Term 2'],
        1,
      ],
    ];
  }

  /**
   * Test you get the parent term correctly from the child term.
   */
  #[Group('TaxonomyHelper')]
  #[DataProvider('getParentTermFromChildTermNameDataProvider')]
  public function testGetParentTermFromChildTermName($child_term_name, $vid, $term_names, $expected_index) {
    $terms = [];
    foreach ($term_names as $name) {
      $term = $this->prophesize(Term::class);
      $term->getName()->willReturn($name);
      $terms[] = $term->reveal();
    }
    $result = $terms[$expected_index];

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

    $this->assertEquals($result, TaxonomyHelper::getParentTermFromChildTermName($child_term_name, $vid), $message);
  }

  /**
   * Data provider for loadMultipleTermsByName.
   */
  public static function loadMultipleTermsByNameDataProvider() {
    $result_terms = array_slice(self::createTaxonomyTree(), 0, 2);

    return [
      [['Term 1', 'Term 2', 'Term 3'], 'test_vocabulary', $result_terms],
      [['Term x', 'Term y', 'Term z'], 'test_vocabulary', NULL],
    ];
  }

  /**
   * Test you can load multiple taxonomy terms by name.
   */
  #[Group('TaxonomyHelper')]
  #[DataProvider('loadMultipleTermsByNameDataProvider')]
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

    $this->assertEquals($result, TaxonomyHelper::loadMultipleTermsByName($names, $vid), $message);
  }

  /**
   * Data provider for loadMultipleTermsByVocabulary.
   */
  public static function loadMultipleTermsByVocabularyDataProvider() {
    $result_terms = array_slice(self::createTaxonomyTree(), 0, 3);

    return [
      ['test_vocabulary', $result_terms],
      ['no_vocabulary', NULL],
    ];
  }

  /**
   * Test you can load multiple terms by vocabulary id.
   */
  #[Group('TaxonomyHelper')]
  #[DataProvider('loadMultipleTermsByVocabularyDataProvider')]
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

    $this->assertEquals($result, TaxonomyHelper::loadMultipleTermsByVocabulary($vid), $message);
  }

  /**
   * Data provider for getTermIdFromOriginalId.
   */
  public static function getTermIdFromOriginalIdDataProvider() {
    return [
      ['125', 'test_vid', '10'],
      ['125', 'null_vid', NULL],
    ];
  }

  /**
   * Test getting term id from original id.
   */
  #[Group('TaxonomyHelper')]
  #[DataProvider('getTermIdFromOriginalIdDataProvider')]
  public function testGetTermIdFromOriginalId($original_id, $vid, $result) {
    // Mock field.
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([['value' => $original_id]]);
    // Mock term.
    $term = $this->prophesize(Term::class);
    $term->hasField('field_original_id')->willReturn(TRUE);
    $term->get('field_original_id')->willReturn($field->reveal());
    $term->id()->willReturn('10');

    $terms = $result !== NULL ? [$term->reveal()] : [];

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

    $this->assertEquals($result, TaxonomyHelper::getTermIdFromOriginalId($original_id, $vid), $message);
  }

  /**
   * Data provider for getTermIdsByFieldValue.
   */
  public static function getTermIdsByFieldValueDataProvider() {
    return [
      ['field_name', 'Jon', 'test_vocab', ['1', '2', '3'], ['1', '2', '3']],
      ['field_name', 'Snow', 'test_vocab', [], NULL],
    ];
  }

  /**
   * Test getting term ids from value of a field.
   */
  #[Group('TaxonomyHelper')]
  #[DataProvider('getTermIdsByFieldValueDataProvider')]
  public function testGetTermIdsByFieldValue($field_name, $value, $vid, $query_result, $result) {
    // Mock entityQuery methods.
    $this->entityQuery->condition($field_name, $value)->willReturn($this->entityQuery);
    $this->entityQuery->condition('vid', $vid)->willReturn($this->entityQuery);
    $this->entityQuery->accessCheck()->willReturn($this->entityQuery);
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

    $this->assertEquals($result, TaxonomyHelper::getTermIdsByFieldValue($field_name, $value, $vid), $message);
  }

  /**
   * Create a taxonomy tree response for loadTree.
   */
  public static function createTaxonomyTree() {
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
