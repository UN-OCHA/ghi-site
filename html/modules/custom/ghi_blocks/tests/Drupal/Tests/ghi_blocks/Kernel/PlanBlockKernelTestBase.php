<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for plan block kernel tests.
 *
 * @group ghi_blocks
 */
abstract class PlanBlockKernelTestBase extends BlockKernelTestBase {

  use BaseObjectTestTrait;
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use SectionTestTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'hpc_api',
    'hpc_common',
    'ghi_plans',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

    $endpoint_query = $this->prophesize(EndpointQuery::class);

    $container = \Drupal::getContainer();
    $container->set('hpc_api.endpoint_query', $endpoint_query->reveal());
    \Drupal::setContainer($container);

    $this->createPlanBaseObjectType();

    $this->createSectionType();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Create a plan base object type.
   */
  private function createPlanBaseObjectType() {
    $this->createBaseObjectType([
      'id' => 'plan',
      'label' => 'Plan',
      'field_year' => 'Year',
    ]);
    $this->createBaseObjectType([
      'id' => 'country',
      'label' => 'Country',
    ]);
    $this->createEntityReferenceField('base_object', 'plan', 'field_country', 'Country', 'base_object', 'default', [
      'target_bundles' => ['country'],
    ]);
    $this->createEntityReferenceField('base_object', 'plan', 'field_focus_country', 'Focus country', 'base_object', 'default', [
      'target_bundles' => ['country'],
    ]);
    $this->createVocabulary(['vid' => 'plan_type']);
    $this->createEntityReferenceField('base_object', 'plan', 'field_plan_type', 'Plan type', 'taxonomy_term', 'default', [
      'target_bundles' => ['plan_type'],
    ]);
    $this->createField('base_object', 'plan', 'string', 'field_plan_version_argument', 'Plan version');
    $this->createField('base_object', 'plan', 'string', 'field_footnotes', 'Footnotes');
  }

  /**
   * Create a plan base object to be used in tests.
   *
   * @param array $values
   *   Optional values for the object creation.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan base object
   */
  protected function createPlanBaseObject(array $values = []) {
    $values['type'] = 'plan';
    if (empty($values['field_year'])) {
      $values['field_year'] = 2024;
    }
    if (empty($values['field_country'])) {
      $values['field_country'] = [
        'target_id' => $this->createBaseObject(['type' => 'country'])->id(),
      ];
    }
    if (empty($values['field_focus_country'])) {
      $values['field_focus_country'] = [
        'target_id' => $this->createBaseObject(['type' => 'country'])->id(),
      ];
    }
    if (empty($values['field_plan_type'])) {
      $plan_type = $this->createTerm(Vocabulary::load('plan_type'));
      $values['field_plan_type'] = [
        'target_id' => $plan_type->id(),
      ];
    }
    $plan = $this->createBaseObject($values);
    return $plan;
  }

  /**
   * Get the necessary contexts for plan sections.
   *
   * @return array
   *   An array of context objects, keyed by the context key.
   */
  protected function getPlanSectionContexts(array $plan_values = [], array $section_values = []) {
    $plan = $this->createPlanBaseObject($plan_values);
    $section_node = $this->createSection($section_values + [
      'label' => 'Section node',
      'field_base_object' => $plan,
    ]);
    return [
      'node' => new EntityContext(new EntityContextDefinition('node'), $section_node),
      'plan' => new EntityContext(new EntityContextDefinition('base_object'), $plan),
    ];
  }

}
