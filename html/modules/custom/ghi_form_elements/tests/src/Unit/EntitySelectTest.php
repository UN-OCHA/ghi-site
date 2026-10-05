<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\ghi_form_elements\Element\EntitySelect;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the entity select form element.
 *
 * @group ghi_form_elements
 */
class EntitySelectTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    drupal_static_reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    drupal_static_reset();
    parent::tearDown();
  }

  /**
   * Tests that processing does not require a remote plan lookup.
   */
  public function testProcessWithoutRemotePlanLookup(): void {
    $plan_id = 1208;
    $plan_name = 'Local plan name';
    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn($plan_id);
    $plan->method('getName')->willReturn($plan_name);

    $entity_query = $this->createMock(EntityQuery::class);
    $entity_query->expects($this->exactly(3))
      ->method('getEntitiesForPlan')
      ->willReturn([]);

    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->expects($this->once())
      ->method('hasDefinition')
      ->with('entity')
      ->willReturn(TRUE);
    $query_manager->expects($this->once())
      ->method('createInstance')
      ->with('entity')
      ->willReturn($entity_query);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.fabric_query_manager', $query_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $element = [
      '#array_parents' => ['entities'],
      '#parents' => ['entities'],
      '#default_value' => ['entity_ids' => []],
      '#element_context' => [
        'base_object' => $plan,
        'plan_object' => $plan,
      ],
      '#include_main_plan' => TRUE,
      '#multiple' => TRUE,
      '#disabled' => FALSE,
    ];

    EntitySelect::processEntitySelect($element, new FormState());

    $this->assertSame($plan_id, $element['entity_ids']['#options'][$plan_id]['id']);
    $this->assertSame($plan_name, $element['entity_ids']['#options'][$plan_id]['name']['data']);
  }

}
